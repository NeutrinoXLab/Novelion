<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CheckoutAttempt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Services\CheckoutAttempts;
use App\Services\StripeReconciler;
use App\Services\StripeService;
use App\Services\StripeStateApplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeObject;
use Tests\Concerns\UsesCommittedDatabase;
use Tests\TestCase;

class StripeRemediationThreeTest extends TestCase
{
    use UsesCommittedDatabase;

    private Order $order;

    private Product $product;

    private CheckoutAttempt $attempt;

    private RemediationThreeClient $api;

    private array $session;

    private array $pi;

    private array $charge;

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.default' => 'null', 'services.stripe.mode' => 'test', 'services.stripe.secret' => 'sk_test_r3_fake', 'services.stripe.webhook_secret' => 'whsec_r3_fake']);
        $user = User::factory()->create();
        $category = Category::create(['name' => 'R3', 'slug' => 'r3']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'R3', 'slug' => 'r3', 'sku' => 'R3', 'selling_price' => 100, 'purchase_price' => 50, 'stock_quantity' => 8]);
        $this->order = Order::create(['user_id' => $user->id, 'order_number' => 'R3-1', 'first_name' => 'R3', 'last_name' => 'Test', 'email' => 'r3@example.test', 'phone' => '0700000000', 'county' => 'Test', 'city' => 'Test', 'address' => 'Test', 'subtotal' => 200, 'shipping_cost' => 0, 'total' => 200, 'payment_method' => 'stripe', 'payment_status' => 'pending', 'status' => 'pending', 'stripe_session_id' => 'cs_r3']);
        OrderItem::create(['order_id' => $this->order->id, 'product_id' => $this->product->id, 'product_name' => 'R3', 'price' => 100, 'quantity' => 2, 'total' => 200]);
        $this->attempt = CheckoutAttempt::create(['token' => (string) Str::uuid(), 'user_id' => $user->id, 'order_id' => $this->order->id, 'session_hash' => str_repeat('a', 64), 'cart_hash' => str_repeat('b', 64), 'request_hash' => str_repeat('c', 64), 'expires_at' => now()->addHour(), 'stripe_started_at' => now()->subHour()]);
        $params = app(StripeService::class)->checkoutParameters($this->order);
        $params['client_reference_id'] = $this->attempt->token;
        $params['metadata'] = ['order_id' => (string) $this->order->id, 'user_id' => (string) $user->id, 'checkout_attempt' => $this->attempt->token, 'request_hash' => $this->attempt->request_hash];
        $params['metadata']['checkout_fingerprint'] = CheckoutAttempts::stripeFingerprint($params);
        $this->attempt->update(['stripe_parameters' => $params]);
        $this->session = ['id' => 'cs_r3', 'object' => 'checkout.session', 'livemode' => false, 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid', 'currency' => 'ron', 'amount_total' => 20000, 'payment_intent' => 'pi_r3', 'client_reference_id' => $this->attempt->token, 'metadata' => $params['metadata']];
        $this->pi = ['id' => 'pi_r3', 'object' => 'payment_intent', 'livemode' => false, 'amount' => 20000, 'amount_received' => 20000, 'currency' => 'ron', 'status' => 'succeeded', 'latest_charge' => 'ch_r3'];
        $this->charge = ['id' => 'ch_r3', 'object' => 'charge', 'livemode' => false, 'amount' => 20000, 'amount_refunded' => 0, 'currency' => 'ron', 'payment_intent' => 'pi_r3', 'paid' => true, 'status' => 'succeeded', 'created' => time() - 600];
        $this->api = new RemediationThreeClient;
        ApiRequestor::setHttpClient($this->api);
        $this->remote();
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        $this->travelBack();
        parent::tearDown();
    }

    private function page(array $data = [], bool $more = false): array
    {
        return ['object' => 'list', 'data' => $data, 'has_more' => $more];
    }

    private function remote(array $refunds = []): void
    {
        $this->api->responses = ['/v1/checkout/sessions/cs_r3' => $this->session, '/v1/payment_intents/pi_r3' => $this->pi, '/v1/charges/ch_r3' => $this->charge, '/v1/refunds' => $this->page($refunds), '/v1/events' => $this->page(), '/v1/checkout/sessions' => $this->page([$this->session])];
    }

    private function failed(): void
    {
        $this->session['payment_status'] = 'unpaid';
        $this->pi['status'] = 'requires_payment_method';
        $this->pi['amount_received'] = 0;
        $this->pi['last_payment_error'] = ['charge' => 'ch_r3'];
        $this->charge['status'] = 'failed';
        $this->charge['paid'] = false;
        $this->remote();
    }

    private function event(?array $object = null, ?int $created = null, string $type = 'checkout.session.async_payment_failed'): array
    {
        return ['id' => 'evt_r3', 'object' => 'event', 'livemode' => $object['livemode'] ?? false, 'type' => $type, 'created' => $created ?? time() - 5, 'data' => ['object' => $object ?? $this->session]];
    }

    private function webhook(array $event, bool $validSignature = true)
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$payload, $validSignature ? 'whsec_r3_fake' : 'whsec_wrong');

        return $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_Stripe-Signature' => "t=$time,v1=$signature"], $payload);
    }

    private function due(): void
    {
        DB::table('orders')->where('id', $this->order->id)->update(['stripe_reconcile_next_at' => null]);
    }

    private function refund(?ReturnRequest $return = null, string $status = 'succeeded'): array
    {
        return ['id' => 're_r3', 'object' => 'refund', 'livemode' => false, 'payment_intent' => 'pi_r3', 'charge' => 'ch_r3', 'currency' => 'ron', 'amount' => $return ? 10000 : 20000, 'status' => $status, 'metadata' => ['order_id' => (string) $this->order->id] + ($return ? ['return_request_id' => (string) $return->id] : [])];
    }

    private function returnRequest(): ReturnRequest
    {
        $return = ReturnRequest::create(['order_id' => $this->order->id, 'user_id' => $this->order->user_id, 'reason' => 'R3', 'status' => 'received', 'requested_at' => now(), 'refund_amount' => 100, 'refund_currency' => 'RON', 'refund_method' => 'stripe']);
        $return->items()->create(['order_item_id' => $this->order->items()->first()->id, 'quantity' => 1, 'unit_price' => 100, 'line_refund_amount' => 100]);

        return $return;
    }

    #[DataProvider('deploymentModes')]
    public function test_disabled_f3_legacy_mode_absent_accepts_canonical_signed_events(string $mode, string $kind): void
    {
        $live = $mode === 'live';
        config(['stripe_reconciliation.enabled' => false, 'services.stripe.mode' => $mode, 'services.stripe.secret' => $live ? 'sk_live_r3_fake' : 'sk_test_r3_fake']);
        $this->assertArrayNotHasKey('livemode', config('stripe_reconciliation'));
        $return = null;
        if (str_contains($kind, 'refund') || $kind === 'charge') {
            DB::table('orders')->where('id', $this->order->id)->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_r3']);
            $return = $kind === 'return_refund' ? $this->returnRequest() : null;
            $object = $this->refund($return, $kind === 'failed_refund' ? 'failed' : 'succeeded');
            $type = $kind === 'failed_refund' ? 'refund.failed' : 'refund.updated';
            if ($kind === 'charge') {
                $object = $this->charge;
                $object['amount_refunded'] = 20000;
                $refund = $this->refund();
                $refund['livemode'] = $live;
                $this->api->responses['/v1/refunds'] = $this->page([$refund]);
                $type = 'charge.refunded';
            }
        } else {
            $object = $this->session;
            $type = $kind === 'async_success' ? 'checkout.session.async_payment_succeeded' : 'checkout.session.completed';
        }
        $object['livemode'] = $live;
        $this->webhook($this->event($object, null, $type))->assertOk()->assertJsonMissing(['ignored' => 'incompatible_context']);
        $this->assertSame($kind === 'failed_refund' ? 'failed' : null, $kind === 'failed_refund' ? $this->order->fresh()->refund_status : null);
        $this->assertSame($return ? 'refunded' : null, $return?->fresh()->status);
        $this->assertSame(str_contains($kind, 'refund') || $kind === 'charge' ? ($return || $kind === 'failed_refund' ? 'paid' : 'refunded') : 'paid', $this->order->fresh()->payment_status);
        $this->assertSame($return ? 9 : (in_array($kind, ['order_refund', 'charge'], true) ? 10 : 8), $this->product->fresh()->stock_quantity);
        $this->assertGreaterThan(0, TransactionalEmail::count());
    }

    public static function deploymentModes(): array
    {
        $cases = [];
        foreach (['live', 'test'] as $mode) {
            foreach (['payment', 'async_success', 'order_refund', 'return_refund', 'failed_refund', 'charge'] as $kind) {
                $cases[] = [$mode, $kind];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidModes')]
    public function test_invalid_canonical_mode_is_retriable_and_never_applies(mixed $mode): void
    {
        config(['services.stripe.mode' => $mode]);
        $this->webhook($this->event($this->session, null, 'checkout.session.completed'))->assertStatus(503);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertCount(0, $this->api->calls);
        $this->webhook($this->event(), false)->assertStatus(400);
        try {
            app(StripeService::class)->read('session', 'cs_r3');
            $this->fail('Invalid configuration reached HTTP');
        } catch (\LogicException $e) {
            $this->assertCount(0, $this->api->calls);
        }
    }

    public static function invalidModes(): array
    {
        return [[null], [''], ['false'], ['true'], ['0'], ['1'], ['off'], ['random'], [false], [true], ['LIVE']];
    }

    public function test_absent_configuration_key_mismatch_and_legitimate_wrong_mode_are_distinct(): void
    {
        config(['services.stripe.mode' => null]);
        $this->webhook($this->event())->assertStatus(503);
        config(['services.stripe.mode' => 'live']);
        $this->webhook($this->event())->assertStatus(503);
        config(['services.stripe.mode' => 'test']);
        $live = $this->session;
        $live['livemode'] = true;
        $this->webhook($this->event($live, null, 'checkout.session.completed'))->assertOk()->assertJson(['ignored' => 'incompatible_context']);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_old_failure_then_current_processing_does_not_cancel_and_reports_waiting(): void
    {
        $this->failed();
        $old = $this->event();
        $this->pi['status'] = 'processing';
        $this->remote();
        $this->assertSame(['payment_waiting' => 1], app(StripeReconciler::class)->run());
        $this->webhook($old)->assertOk()->assertJson(['result' => 'payment_waiting']);
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame('payment_waiting', $this->order->fresh()->stripe_reconcile_result);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_processing_then_newer_definitive_failure_duplicates_and_later_success(): void
    {
        $this->failed();
        $this->pi['status'] = 'processing';
        $this->remote();
        $this->assertSame(['payment_waiting' => 1], app(StripeReconciler::class)->run());
        $this->pi['status'] = 'requires_payment_method';
        $this->remote();
        $failure = $this->event();
        $this->webhook($failure)->assertOk()->assertJson(['result' => 'async_failed']);
        $this->webhook($failure)->assertOk();
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame('failed', $this->order->fresh()->payment_status);
        $this->assertSame('async_failed', $this->order->fresh()->stripe_reconcile_result);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('kind', 'cancelled')->count());
        $paid = array_replace($this->session, ['payment_status' => 'paid']);
        $this->webhook($this->event($paid, null, 'checkout.session.async_payment_succeeded'))->assertOk();
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertNotNull($this->order->fresh()->late_stripe_payment_at);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::where('kind', 'paid')->count());
    }

    public function test_old_failure_current_paid_wins(): void
    {
        $unpaid = array_replace($this->session, ['payment_status' => 'unpaid']);
        $this->webhook($this->event($unpaid))->assertOk()->assertJson(['result' => 'paid']);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('kind', 'paid')->count());
        $this->assertSame(0, TransactionalEmail::where('kind', 'cancelled')->count());
    }

    #[DataProvider('unsafeFailures')]
    public function test_wrong_identity_or_ambiguous_failure_never_cancels(string $case): void
    {
        $this->failed();
        $event = $this->event();
        if ($case === 'session') {
            $event['data']['object']['id'] = 'cs_other';
        }
        if ($case === 'pi') {
            $event['data']['object']['payment_intent'] = 'pi_other';
        }
        if ($case === 'attempt') {
            $event['data']['object']['metadata']['checkout_attempt'] = 'other';
        }
        if ($case === 'equal') {
            $event['created'] = $this->charge['created'];
        }
        if ($case === 'older_charge') {
            $event['created'] = $this->charge['created'] - 1;
        }
        if ($case === 'missing_error') {
            unset($this->pi['last_payment_error']);
            $this->remote();
        }
        $this->webhook($event)->assertOk()->assertJson(['result' => 'manual_review']);
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function unsafeFailures(): array
    {
        return [['session'], ['pi'], ['attempt'], ['equal'], ['older_charge'], ['missing_error']];
    }

    public function test_pi_progress_during_failed_charge_http_and_webhook_local_success_win(): void
    {
        $this->failed();
        $this->api->responses['/v1/charges/ch_r3'] = function () {
            $this->api->responses['/v1/payment_intents/pi_r3'] = array_replace($this->pi, ['status' => 'processing']);

            return $this->charge;
        };
        $this->webhook($this->event())->assertOk()->assertJson(['result' => 'payment_waiting']);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_f3_async_proof_crosses_budget_boundary_and_claim_loss_has_no_effect(): void
    {
        $this->failed();
        config(['stripe_reconciliation.read_budget' => 4]);
        $this->api->responses['/v1/events'] = $this->page([$this->event()]);
        $this->assertSame(['scan_incomplete' => 1], app(StripeReconciler::class)->run());
        $this->assertSame('async_proof', $this->order->fresh()->stripe_reconcile_cursor['phase']);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->due();
        $this->api->responses['/v1/charges/ch_r3'] = function () {
            DB::table('orders')->where('id', $this->order->id)->update(['stripe_reconcile_claim' => 'worker-b', 'stripe_reconcile_next_at' => now()->addHour(), 'stripe_reconcile_cursor' => json_encode(['B' => 'cursor']), 'stripe_reconcile_result' => 'payment_waiting']);

            return $this->charge;
        };
        $this->assertSame(['claim_lost' => 1], app(StripeReconciler::class)->run());
        $this->assertSame(['B' => 'cursor'], $this->order->fresh()->stripe_reconcile_cursor);
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_f3_and_webhook_share_definitive_failure_policy(): void
    {
        $this->failed();
        $this->api->responses['/v1/events'] = $this->page([$this->event()]);
        $this->assertSame(['async_failed' => 1], app(StripeReconciler::class)->run());
        $this->webhook($this->event())->assertOk()->assertJson(['result' => 'async_failed']);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('kind', 'cancelled')->count());
    }

    public function test_webhook_paid_during_async_http_revalidates_local_terminal_state(): void
    {
        $paid = $this->session;
        $this->failed();
        $this->api->responses['/v1/charges/ch_r3'] = function () use ($paid) {
            $this->webhook($this->event($paid, null, 'checkout.session.completed'))->assertOk();

            return $this->charge;
        };
        $this->webhook($this->event())->assertOk()->assertJson(['result' => 'terminal_local_state']);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('kind', 'paid')->count());
        $this->assertSame(0, TransactionalEmail::where('kind', 'cancelled')->count());
    }

    public function test_same_claim_can_retry_after_business_rollback(): void
    {
        $this->order->update(['stripe_session_id' => null, 'stripe_reconcile_claim' => 'same-claim', 'stripe_reconcile_next_at' => now()->addMinutes(4)]);
        $context = StripeReconciler::snapshotContext($this->order->fresh(), $this->attempt);
        $fired = false;
        DB::listen(function ($query) use (&$fired) {
            if (! $fired && str_starts_with($query->sql, 'update "orders" set "stripe_session_id"')) {
                $fired = true;
                throw new \RuntimeException('R3 same claim rollback');
            }
        });
        $apply = fn () => app(StripeStateApplier::class)->recovered($this->order->id, StripeObject::constructFrom($this->session), StripeObject::constructFrom($this->pi), StripeObject::constructFrom($this->charge), [], 'same-claim', $context);
        $caught = null;
        try {
            $apply();
        } catch (\RuntimeException $e) {
            $caught = $e;
            $this->assertTrue($fired);
        }
        $this->assertNotNull($caught, 'Injection did not fire');
        $this->assertSame('same-claim', $this->order->fresh()->stripe_reconcile_claim);
        $this->assertNull($this->order->fresh()->stripe_session_id);
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertSame('paid', $apply());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('kind', 'paid')->count());
    }

    #[DataProvider('rollbackEffects')]
    public function test_business_commit_and_expired_completion_remain_idempotent(string $effect): void
    {
        $this->travelTo(now()->startOfSecond());
        $refunds = [];
        if ($effect === 'expired') {
            $this->session = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        }
        if ($effect === 'refund') {
            $this->charge['amount_refunded'] = 20000;
            $refunds = [$this->refund()];
        }
        $this->remote($refunds);
        $applier = new class($this) extends StripeStateApplier
        {
            public function __construct(private StripeRemediationThreeTest $test) {}

            public function recovered(int $orderId, object $session, ?object $pi, ?object $charge, array $refunds, string $claimId, string $expectedContext, ?object $asyncFailure = null): string
            {
                $result = parent::recovered($orderId, $session, $pi, $charge, $refunds, $claimId, $expectedContext, $asyncFailure);
                $this->test->travel(241)->seconds();

                return $result;
            }
        };
        $this->assertSame(['completion_not_owned' => 1], (new StripeReconciler(app(StripeService::class), $applier))->run());
        $state = $this->order->fresh()->payment_status;
        $stock = $this->product->fresh()->stock_quantity;
        $receipts = TransactionalEmail::pluck('event_key')->sort()->values()->all();
        $this->assertSame(['paid' => 'paid', 'expired' => 'failed', 'refund' => 'refunded'][$effect], $state);
        $result = app(StripeReconciler::class)->run();
        $this->assertArrayNotHasKey('completion_not_owned', $result);
        $this->assertSame($state, $this->order->fresh()->payment_status);
        $this->assertSame($stock, $this->product->fresh()->stock_quantity);
        $this->assertSame($receipts, TransactionalEmail::pluck('event_key')->sort()->values()->all());
    }

    public function test_global_page_limit_stops_incomplete_discovery_without_business_effects(): void
    {
        config(['stripe_reconciliation.read_budget' => 4, 'stripe_reconciliation.page_size' => 1, 'stripe_reconciliation.scan_max_pages' => 5]);
        $this->order->update(['stripe_session_id' => null]);
        $entries = [];
        for ($i = 0; $i < 8; $i++) {
            $entries[] = array_replace($this->session, ['id' => 'cs_limit_'.$i, 'metadata' => []]);
        }
        $this->paginated('/v1/checkout/sessions', $entries);
        $this->assertSame(['scan_incomplete' => 1], app(StripeReconciler::class)->run());
        $this->due();
        $this->assertSame(['manual_review' => 1], app(StripeReconciler::class)->run());
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertCount(5, $this->api->calls);
    }

    public function test_expired_cursor_stops_before_stripe_http_or_business_effects(): void
    {
        config(['stripe_reconciliation.read_budget' => 4, 'stripe_reconciliation.page_size' => 1]);
        $this->order->update(['stripe_session_id' => null]);
        $entries = [];
        for ($i = 0; $i < 8; $i++) {
            $entries[] = array_replace($this->session, ['id' => 'cs_age_'.$i, 'metadata' => []]);
        }
        $this->paginated('/v1/checkout/sessions', $entries);
        $this->assertSame(['scan_incomplete' => 1], app(StripeReconciler::class)->run());
        $cursor = $this->order->fresh()->stripe_reconcile_cursor;
        $cursor['started_at'] = time() - 86401;
        $this->order->update(['stripe_reconcile_cursor' => $cursor]);
        $this->api->calls = [];
        $this->due();
        $this->assertSame(['manual_review' => 1], app(StripeReconciler::class)->run());
        $this->assertCount(0, $this->api->calls);
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    private function paginated(string $path, array $entries): void
    {
        $this->api->responses[$path] = function ($params) use ($entries) {
            $ids = array_column($entries, 'id');
            $index = isset($params['starting_after']) ? array_search($params['starting_after'], $ids, true) + 1 : 0;
            $limit = (int) $params['limit'];

            return $this->page(array_slice($entries, $index, $limit), $index + $limit < count($entries));
        };
    }

    public function test_discovery_more_pages_than_budget_makes_verified_forward_progress(): void
    {
        config(['stripe_reconciliation.read_budget' => 4, 'stripe_reconciliation.page_size' => 1]);
        $this->order->update(['stripe_session_id' => null]);
        $entries = [];
        for ($i = 0; $i < 9; $i++) {
            $entries[] = array_replace($this->session, ['id' => 'cs_unrelated_'.$i, 'metadata' => []]);
        }
        $entries[] = $this->session;
        $this->paginated('/v1/checkout/sessions', $entries);
        for ($run = 0; $run < 9; $run++) {
            $this->due();
            $result = (new StripeReconciler(app(StripeService::class), app(StripeStateApplier::class)))->run();
            if (isset($result['paid'])) {
                break;
            }
            $this->assertSame(['scan_incomplete' => 1], $result);
            $this->assertSame('pending', $this->order->fresh()->payment_status);
            $this->assertSame(0, TransactionalEmail::count());
        }
        $this->assertSame(['paid' => 1], $result);
        $this->assertSame('cs_r3', $this->order->fresh()->stripe_session_id);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('kind', 'paid')->count());
        // Two incremental passes plus a complete current-run verification (limit 100).
        $this->assertSame(21, count(array_filter($this->api->calls, fn ($call) => $call['path'] === '/v1/checkout/sessions')));
    }

    public function test_refunds_more_pages_than_budget_refresh_then_complete_without_partial_effects(): void
    {
        config(['stripe_reconciliation.read_budget' => 4, 'stripe_reconciliation.page_size' => 1]);
        $this->charge['amount_refunded'] = 20000;
        $this->remote();
        $entries = [];
        for ($i = 0; $i < 8; $i++) {
            $entries[] = $refund = array_replace($this->refund(), ['id' => 're_'.$i, 'amount' => 2500]);
            $this->api->responses['/v1/refunds/re_'.$i] = $refund;
        }
        $this->paginated('/v1/refunds', $entries);
        for ($run = 0; $run < 20; $run++) {
            $this->due();
            $result = (new StripeReconciler(app(StripeService::class), app(StripeStateApplier::class)))->run();
            if (isset($result['refund_completed'])) {
                break;
            }
            $this->assertSame(['scan_incomplete' => 1], $result);
            $this->assertSame(8, $this->product->fresh()->stock_quantity);
            $this->assertSame(0, TransactionalEmail::count());
        }
        $this->assertSame(['refund_completed' => 1], $result);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':refund:completed')->count());
        $this->due();
        config(['stripe_reconciliation.read_budget' => 100]);
        app(StripeReconciler::class)->run();
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }

    #[DataProvider('rollbackEffects')]
    public function test_business_rollback_discards_consumed_cursor_and_new_claim_retries(string $effect): void
    {
        $this->order->update(['stripe_session_id' => null]);
        if ($effect === 'expired') {
            $this->session = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        }
        $refunds = [];
        if ($effect === 'refund') {
            $this->charge['amount_refunded'] = 20000;
            $refunds = [$this->refund()];
        }
        $this->remote($refunds);
        $fired = false;
        DB::listen(function ($query) use (&$fired) {
            if (! $fired && str_starts_with($query->sql, 'update "orders" set "stripe_session_id"')) {
                $fired = true;
                throw new \RuntimeException('R3 injected rollback after binding');
            }
        });
        $this->assertSame(['api_or_local_error' => 1], app(StripeReconciler::class)->run());
        $this->assertTrue($fired);
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $this->assertNull($this->order->fresh()->stripe_session_id);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
        $this->due();
        $expected = ['paid' => 'paid', 'expired' => 'expired_or_canceled', 'refund' => 'refund_completed'][$effect];
        $this->assertSame([$expected => 1], app(StripeReconciler::class)->run());
        $this->assertSame($effect === 'paid' ? 8 : 10, $this->product->fresh()->stock_quantity);
    }

    public static function rollbackEffects(): array
    {
        return [['paid'], ['expired'], ['refund']];
    }
}

class RemediationThreeClient implements ClientInterface
{
    public array $responses = [];

    public array $calls = [];

    public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Stripe HTTP under transaction');
        }
        $path = parse_url($url, PHP_URL_PATH);
        $this->calls[] = ['path' => $path, 'method' => $method, 'params' => $params, 'transaction' => DB::transactionLevel()];
        $response = $this->responses[$path] ?? throw new \LogicException('Unmocked HTTP blocked: '.$path);
        if (is_callable($response)) {
            $response = $response($params);
        }

        return [json_encode($response, JSON_THROW_ON_ERROR), 200, []];
    }
}
