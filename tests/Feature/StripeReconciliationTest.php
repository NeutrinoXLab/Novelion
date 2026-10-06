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
use App\Services\StripeModePolicy;
use App\Services\StripeReconciler;
use App\Services\StripeService;
use App\Services\StripeStateApplier;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeObject;
use Tests\Concerns\UsesCommittedDatabase;
use Tests\TestCase;

class StripeReconciliationTest extends TestCase
{
    use UsesCommittedDatabase;

    private Order $order;

    private Product $product;

    private CheckoutAttempt $attempt;

    private ReconciliationStripeClient $api;

    private array $session;

    private array $pi;

    private array $charge;

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.default' => 'null', 'services.stripe.secret' => 'sk_test_f3_fake',
            'services.stripe.webhook_secret' => 'whsec_f3_fake', 'services.stripe.mode' => 'test']);
        $user = User::factory()->create();
        $category = Category::create(['name' => 'F3', 'slug' => 'f3']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'F3 product',
            'slug' => 'f3', 'sku' => 'F3', 'purchase_price' => 50, 'selling_price' => 100, 'stock_quantity' => 8]);
        $this->order = Order::create(['user_id' => $user->id, 'order_number' => 'F3-1', 'first_name' => 'F3',
            'last_name' => 'Test', 'email' => 'f3@example.test', 'phone' => '0700000000',
            'county' => 'Test', 'city' => 'Test', 'address' => 'Test', 'subtotal' => 200, 'shipping_cost' => 0,
            'total' => 200, 'payment_method' => 'stripe', 'payment_status' => 'pending', 'status' => 'pending',
            'stripe_session_id' => 'cs_f3']);
        OrderItem::create(['order_id' => $this->order->id, 'product_id' => $this->product->id,
            'product_name' => $this->product->name, 'price' => 100, 'quantity' => 2, 'total' => 200]);
        $this->attempt = CheckoutAttempt::create(['token' => (string) Str::uuid(), 'user_id' => $user->id,
            'order_id' => $this->order->id, 'session_hash' => str_repeat('a', 64), 'cart_hash' => str_repeat('b', 64),
            'request_hash' => str_repeat('c', 64), 'expires_at' => now()->addHours(2), 'stripe_started_at' => now()->subHours(25)]);
        $params = app(StripeService::class)->checkoutParameters($this->order);
        $params['client_reference_id'] = $this->attempt->token;
        $params['metadata'] = ['order_id' => (string) $this->order->id, 'checkout_attempt' => $this->attempt->token,
            'user_id' => (string) $user->id, 'request_hash' => $this->attempt->request_hash];
        $params['metadata']['checkout_fingerprint'] = CheckoutAttempts::stripeFingerprint($params);
        $this->attempt->update(['stripe_parameters' => $params]);
        $this->session = ['id' => 'cs_f3', 'object' => 'checkout.session', 'livemode' => false,
            'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid', 'currency' => 'ron',
            'amount_total' => 20000, 'payment_intent' => 'pi_f3', 'metadata' => $params['metadata'],
            'client_reference_id' => $this->attempt->token];
        $this->pi = ['id' => 'pi_f3', 'object' => 'payment_intent', 'livemode' => false, 'currency' => 'ron',
            'amount' => 20000, 'amount_received' => 20000, 'status' => 'succeeded', 'latest_charge' => 'ch_f3'];
        $this->charge = ['id' => 'ch_f3', 'object' => 'charge', 'livemode' => false, 'currency' => 'ron',
            'amount' => 20000, 'amount_refunded' => 0, 'paid' => true, 'payment_intent' => 'pi_f3'];
        $this->api = new ReconciliationStripeClient;
        ApiRequestor::setHttpClient($this->api);
        $this->remote();
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    private function remote(array $refunds = []): void
    {
        $this->api->responses = [
            '/v1/checkout/sessions/cs_f3' => $this->session,
            '/v1/payment_intents/pi_f3' => $this->pi,
            '/v1/payment_intents/pi_other' => $this->pi,
            '/v1/charges/ch_f3' => $this->charge,
            '/v1/refunds' => $this->page($refunds),
            '/v1/events' => $this->page([]),
            '/v1/checkout/sessions' => $this->page([$this->session]),
        ];
    }

    private function page(array $data, bool $more = false): array
    {
        return ['object' => 'list', 'has_more' => $more, 'data' => $data];
    }

    private function definitiveAsyncFailure(): void
    {
        $this->session['payment_status'] = 'unpaid';
        $this->pi['status'] = 'requires_payment_method';
        $this->pi['amount_received'] = 0;
        $this->pi['last_payment_error'] = ['charge' => 'ch_f3'];
        $this->charge['paid'] = false;
        $this->charge['status'] = 'failed';
        $this->charge['created'] = time() - 60;
        $this->remote();
    }

    private function reconcile(): string
    {
        app(StripeReconciler::class)->run();

        return $this->order->fresh()->stripe_reconcile_result;
    }

    private function due(): void
    {
        $this->order->refresh()->update(['stripe_reconcile_next_at' => null]);
    }

    private function refund(string $status = 'succeeded', int $amount = 20000, ?ReturnRequest $return = null): array
    {
        return ['id' => 're_f3', 'object' => 'refund', 'livemode' => false, 'currency' => 'ron',
            'payment_intent' => 'pi_f3', 'charge' => 'ch_f3', 'amount' => $amount, 'status' => $status,
            'metadata' => $return ? ['order_id' => (string) $this->order->id, 'return_request_id' => (string) $return->id]
                : ['order_id' => (string) $this->order->id]];
    }

    private function returnRequest(int $amount = 100): ReturnRequest
    {
        $return = ReturnRequest::create(['order_id' => $this->order->id, 'user_id' => $this->order->user_id,
            'reason' => 'F3', 'status' => 'received', 'requested_at' => now(), 'refund_amount' => $amount,
            'refund_currency' => 'RON', 'refund_method' => 'stripe']);
        $return->items()->create(['order_item_id' => $this->order->items->first()->id, 'quantity' => 1, 'unit_price' => 100, 'line_refund_amount' => 100]);

        return $return;
    }

    public static function paymentLogisticsStates(): array
    {
        return [['shipped', null], ['delivered', null], ['processing', 'shipped_at'],
            ['processing', 'delivered_at'], ['pending', null], ['new', null]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('paymentLogisticsStates')]
    public function test_payment_confirmation_preserves_dispatch_before_refund(string $status, ?string $timestamp): void
    {
        config(['stripe_reconciliation.enabled' => false]);
        $this->order->update(['status' => $status, 'shipped_at' => $timestamp === 'shipped_at' ? now() : null,
            'delivered_at' => $timestamp === 'delivered_at' ? now() : null]);
        $timestamps = $this->order->fresh()->only(['shipped_at', 'delivered_at']);
        $history = ! in_array($status, ['pending', 'new'], true);
        $this->assertSame($history, $this->order->fresh()->hasDispatchHistory());
        $this->assertSame('paid', $this->reconcile());
        $this->due();
        $this->assertSame('paid', $this->reconcile());
        $current = $this->order->fresh();
        $this->assertSame('paid', $current->payment_status);
        $this->assertSame($history ? $status : 'processing', $current->status);
        $this->assertEquals($timestamps, $current->only(['shipped_at', 'delivered_at']));
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, \App\Models\TransactionalEmail::where('event_key', 'order:'.$this->order->id.':paid')->count());
        if (! $history) {
            return;
        }
        $this->charge['amount_refunded'] = 20000;
        $this->remote([$this->refund()]);
        $refundedAt = null;
        foreach ([1, 2] as $replay) {
            $this->due();
            $this->assertSame('manual_review', $this->reconcile());
            $current = $this->order->fresh();
            $this->assertSame('refunded', $current->payment_status);
            $this->assertSame('completed', $current->refund_status);
            $this->assertSame($status, $current->status);
            $this->assertTrue($current->hasDispatchHistory());
            $this->assertEquals($timestamps, $current->only(['shipped_at', 'delivered_at']));
            $this->assertNull($current->stock_restored_at);
            $this->assertSame(8, $this->product->fresh()->stock_quantity);
            foreach (['cancel', 'restoreStock'] as $operation) {
                $caught = null;
                try {
                    app(\App\Services\OrderService::class)->$operation($this->order);
                } catch (\RuntimeException $exception) {
                    $caught = $exception;
                }
                $this->assertNotNull($caught, $operation.' must reject dispatched goods.');
            }
            $this->assertSame(8, $this->product->fresh()->stock_quantity);
            $this->assertNull($this->order->fresh()->stock_restored_at);
            $this->assertSame('manual_review', $current->stripe_reconcile_result);
            $this->assertNotNull($current->refunded_at);
            if ($refundedAt !== null) {
                $this->assertEquals($refundedAt, $current->refunded_at);
            }
            $refundedAt = $current->refunded_at;
        }
        $this->assertSame(1, \App\Models\TransactionalEmail::where('event_key', 'order:'.$this->order->id.':refund:completed')->count());
        $this->assertSame(0, \App\Models\TransactionalEmail::where('kind', 'cancelled')->count());
    }

    public function test_paid_without_browser_or_webhook_and_duplicate_reconciliation(): void
    {
        $this->assertSame('paid', $this->reconcile());
        $this->due();
        $this->assertSame('paid', $this->reconcile());
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':paid')->count());
        $this->assertSame(['get'], array_values(array_unique(array_column($this->api->calls, 'method'))));
    }

    public function test_expired_without_browser_or_webhook_restores_once(): void
    {
        $this->session = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        $this->remote();
        $this->assertSame('expired_or_canceled', $this->reconcile());
        $this->due();
        $this->reconcile();
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame('failed', $this->order->fresh()->payment_status);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':cancelled')->count());
    }

    public function test_missing_session_zero_results_is_unknown_not_failure(): void
    {
        $this->order->update(['stripe_session_id' => null]);
        $this->api->responses['/v1/checkout/sessions'] = $this->page([]);
        $this->assertSame('session_not_found', $this->reconcile());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_missing_session_one_match_and_over_23_hours_only_get(): void
    {
        $this->order->update(['stripe_session_id' => null]);
        $this->assertSame('paid', $this->reconcile());
        $this->assertSame('cs_f3', $this->order->fresh()->stripe_session_id);
        $this->assertCount(6, $this->api->calls);
        foreach ($this->api->calls as $call) {
            $this->assertSame('get', $call['method']);
            $this->assertSame(0, $call['transaction']);
        }
    }

    public function test_multiple_discovered_sessions_require_manual_review(): void
    {
        $this->order->update(['stripe_session_id' => null]);
        $this->api->responses['/v1/checkout/sessions'] = $this->page([$this->session, array_replace($this->session, ['id' => 'cs_other'])]);
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertNull($this->order->fresh()->stripe_session_id);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
    }

    public function test_resumed_discovery_rejects_changed_verification_pass_without_business_effects(): void
    {
        config(['stripe_reconciliation.read_budget' => 4]);
        $this->order->update(['stripe_session_id' => null]);
        $pages = [];
        for ($i = 1; $i <= 4; $i++) {
            $pages[] = $this->page([array_replace($this->session, ['id' => 'cs_unrelated_'.$i, 'metadata' => []])], true);
        }
        $pages[] = $this->page([$this->session]);
        $this->api->queues['/v1/checkout/sessions'] = $pages;
        $this->assertSame('scan_incomplete', $this->reconcile());
        $this->assertSame('cs_unrelated_4', $this->order->fresh()->stripe_reconcile_cursor['after']);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->due();
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $this->assertSame('cs_unrelated_4', $this->api->calls[4]['params']['starting_after']);
    }

    #[DataProvider('waitingStates')]
    public function test_nonterminal_pi_never_releases_stock(string $status): void
    {
        $this->session['payment_status'] = 'unpaid';
        $this->pi['status'] = $status;
        $this->attempt->update(['stripe_started_at' => now()->subHours(2)]);
        $this->pi['amount_received'] = 0;
        $this->remote();
        $this->assertSame('payment_waiting', $this->reconcile());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function waitingStates(): array
    {
        return array_map(fn ($s) => [$s], ['processing', 'requires_action', 'requires_capture', 'requires_confirmation', 'requires_payment_method']);
    }

    public function test_async_get_success_and_definitive_canceled_failure(): void
    {
        $this->session['payment_status'] = 'unpaid';
        $this->pi['status'] = 'processing';
        $this->remote();
        $this->assertSame('payment_waiting', $this->reconcile());
        $this->session['payment_status'] = 'paid';
        $this->pi['status'] = 'succeeded';
        $this->remote();
        $this->due();
        $this->assertSame('paid', $this->reconcile());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
    }

    public function test_complete_session_with_canceled_pi_releases_once(): void
    {
        $this->session['payment_status'] = 'unpaid';
        $this->pi['status'] = 'canceled';
        $this->remote();
        $this->assertSame('expired_or_canceled', $this->reconcile());
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }

    public function test_late_paid_after_local_cancel_records_marker_without_reactivation(): void
    {
        $this->order->update(['status' => 'cancelled', 'payment_status' => 'failed', 'stock_restored_at' => now()]);
        $this->product->update(['stock_quantity' => 10]);
        $this->assertSame('late_paid_manual_review', $this->reconcile());
        $this->assertNotNull($this->order->fresh()->late_stripe_payment_at);
        $this->assertSame('failed', $this->order->fresh()->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::where('kind', 'paid')->count());
    }

    public function test_full_refund_is_recovered_before_obsolete_paid_receipt(): void
    {
        $this->charge['amount_refunded'] = 20000;
        $this->remote([$this->refund()]);
        $this->assertSame('refund_completed', $this->reconcile());
        $this->due();
        $this->assertSame('refund_completed', $this->reconcile());
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':paid')->count());
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':refund:completed')->count());
    }

    public function test_unclassified_partial_refund_requires_manual_review_without_full_restore(): void
    {
        $this->charge['amount_refunded'] = 10000;
        $this->remote([$this->refund('succeeded', 10000)]);
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
    }

    public function test_return_partial_refund_restores_only_returned_quantity_once(): void
    {
        $return = $this->returnRequest();
        $this->charge['amount_refunded'] = 10000;
        $this->remote([$this->refund('succeeded', 10000, $return)]);
        $this->assertSame('return_refund_reconciled', $this->reconcile());
        $this->due();
        $this->reconcile();
        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'return:'.$return->id.':refund:completed')->count());
    }

    #[DataProvider('staleRefundStates')]
    public function test_stale_order_refund_observation_cannot_undo_completed(string $status): void
    {
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3']);
        $staleModel = $this->order->fresh();
        $staleObservation = StripeObject::constructFrom($this->refund($status));
        $applier = app(StripeStateApplier::class);
        $applier->refund(StripeObject::constructFrom($this->refund()));
        $applier->refund($staleObservation, $status === 'failed');
        $this->assertSame('paid', $staleModel->payment_status);
        $this->assertSame('completed', $this->order->fresh()->refund_status);
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':refund:completed')->count());
    }

    public static function staleRefundStates(): array
    {
        return [['pending'], ['failed'], ['canceled']];
    }

    #[DataProvider('staleRefundStates')]
    public function test_stale_return_refund_observation_cannot_undo_completed(string $status): void
    {
        $return = $this->returnRequest();
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3']);
        $staleModel = $return->fresh();
        $stale = StripeObject::constructFrom($this->refund($status, 10000, $return));
        $applier = app(StripeStateApplier::class);
        $applier->refund(StripeObject::constructFrom($this->refund('succeeded', 10000, $return)));
        $applier->refund($stale);
        $this->assertSame('received', $staleModel->status);
        $this->assertSame('completed', $return->fresh()->refund_status);
        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'return:'.$return->id.':refund:completed')->count());
    }

    public function test_delayed_refund_post_response_after_succeeded_webhook_uses_same_guard(): void
    {
        $this->order->update(['payment_status' => 'paid', 'status' => 'processing', 'stripe_payment_intent' => 'pi_f3']);
        $this->api->responses['/v1/refunds'] = function (): array {
            $this->webhook('refund.updated', $this->refund())->assertOk();

            return $this->refund('pending');
        };
        app(StripeService::class)->refundPayment($this->order->fresh());
        $this->assertSame('completed', $this->order->fresh()->refund_status);
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':refund:completed')->count());
    }

    public function test_delayed_return_post_response_after_succeeded_webhook_uses_same_guard(): void
    {
        $return = $this->returnRequest();
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3']);
        $this->api->responses['/v1/refunds'] = function () use ($return): array {
            $this->webhook('refund.updated', $this->refund('succeeded', 10000, $return))->assertOk();

            return $this->refund('pending', 10000, $return);
        };
        app(StripeService::class)->refundReturn($return->fresh());
        $this->assertSame('completed', $return->fresh()->refund_status);
        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'return:'.$return->id.':refund:completed')->count());
    }

    #[DataProvider('apiErrors')]
    public function test_api_errors_backoff_without_business_effects(int $status): void
    {
        $this->api->errors['/v1/checkout/sessions/cs_f3'] = $status;
        $this->reconcile();
        $first = $this->order->fresh()->stripe_reconcile_next_at;
        $this->due();
        $this->reconcile();
        $this->assertSame(2, $this->order->fresh()->stripe_reconcile_failures);
        $this->assertTrue($this->order->fresh()->stripe_reconcile_next_at->gt($first));
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function apiErrors(): array
    {
        return [[404], [429], [500], [0]];
    }

    public function test_business_failure_rolls_back_stock_order_and_outbox_then_retry_recovers(): void
    {
        $this->session = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        $this->remote();
        DB::unprepared("CREATE TEMP TRIGGER fail_f3_stock BEFORE UPDATE OF stock_quantity ON products BEGIN SELECT RAISE(ABORT, 'F3 rollback'); END");
        $this->assertSame('api_or_local_error', $this->reconcile());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertNull($this->order->fresh()->stock_restored_at);
        $this->assertSame(0, TransactionalEmail::count());
        DB::unprepared('DROP TRIGGER fail_f3_stock');
        $this->due();
        $this->assertSame('expired_or_canceled', $this->reconcile());
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }

    public function test_expired_worker_lease_resumes_and_oldest_due_work_is_fair(): void
    {
        config(['stripe_reconciliation.batch' => 1]);
        $other = $this->order->replicate();
        $other->order_number = 'F3-2';
        $other->stripe_session_id = 'cs_other';
        $other->save();
        $this->order->update(['stripe_reconcile_next_at' => now()->addMinutes(4)]);
        $this->api->responses['/v1/checkout/sessions/cs_other'] = $this->session;
        app(StripeReconciler::class)->run();
        $this->assertSame('manual_review', $other->fresh()->stripe_reconcile_result);
        $this->assertNull($this->order->fresh()->stripe_reconcile_result);
        $this->travel(5)->minutes();
        $this->assertSame('paid', $this->reconcile());
        $this->assertSame(0, $this->order->fresh()->stripe_reconcile_failures);
    }

    public function test_disabled_command_does_not_read_stripe_or_modify_operational_fields(): void
    {
        $this->artisan('stripe:reconcile')->assertSuccessful();
        $this->assertSame([], $this->api->calls);
        $this->assertNull($this->order->fresh()->stripe_reconcile_next_at);
    }

    public function test_get_is_rejected_inside_transaction_before_network_dispatch(): void
    {
        try {
            DB::transaction(fn () => app(StripeService::class)->read('session', 'cs_f3'));
            $this->fail('GET under transaction was allowed.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('DB transaction', $e->getMessage());
        }
        $this->assertSame([], $this->api->calls);
    }

    #[DataProvider('invalidSessions')]
    public function test_recovered_session_rejects_unsafe_context_or_correlation(string $field, mixed $value): void
    {
        if (str_starts_with($field, 'metadata.')) {
            $this->session['metadata'][substr($field, 9)] = $value;
        } else {
            $this->session[$field] = $value;
        }
        $this->remote();
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function invalidSessions(): array
    {
        return [['livemode', true], ['livemode', null], ['id', 'cs_other'], ['mode', 'subscription'],
            ['currency', 'eur'], ['amount_total', 19999], ['client_reference_id', 'wrong'],
            ['metadata.order_id', '9999'], ['metadata.checkout_attempt', 'wrong'], ['metadata.user_id', '9999'],
            ['metadata.request_hash', 'wrong'], ['metadata.checkout_fingerprint', 'wrong'], ['payment_intent', 'pi_other']];
    }

    public function test_legacy_without_durable_f2_correlation_is_manual(): void
    {
        $this->attempt->update(['stripe_parameters' => null]);
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame([], $this->api->calls);
    }

    public function test_refund_pages_resume_and_do_not_apply_partial_scan(): void
    {
        config(['stripe_reconciliation.read_budget' => 4]);
        $this->charge['amount_refunded'] = 20000;
        $this->remote();
        $first = $this->refund('succeeded', 10000);
        $second = array_replace($first, ['id' => 're_second']);
        $this->api->queues['/v1/refunds'] = [$this->page([$first], true), $this->page([$second])];
        $this->api->responses['/v1/refunds/re_f3'] = $first;
        $this->api->responses['/v1/refunds/re_second'] = $second;
        $this->api->responses['/v1/refunds'] = fn ($params) => isset($params['starting_after'])
            ? $this->page([$second]) : $this->page([$first, $second]);
        $this->assertSame('scan_incomplete', $this->reconcile());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->due();
        $this->assertSame('refund_completed', $this->reconcile());
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        for ($i = 0; $i < 3; $i++) {
            $this->due();
            $result = $this->reconcile();
        }
        $this->assertSame('refund_completed', $result);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }

    #[DataProvider('invalidPaymentIntents')]
    public function test_payment_intent_amount_currency_received_and_context_are_validated(string $field, mixed $value): void
    {
        $this->pi[$field] = $value;
        $this->remote();
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function invalidPaymentIntents(): array
    {
        return [['id', 'pi_wrong'], ['amount', 19999], ['amount_received', 19999], ['currency', 'eur'],
            ['livemode', true], ['status', 'processing']];
    }

    #[DataProvider('invalidRefunds')]
    public function test_recovered_refund_requires_charge_pi_context_and_amount(string $field, mixed $value): void
    {
        $this->charge['amount_refunded'] = 20000;
        $refund = $this->refund();
        $refund[$field] = $value;
        $this->remote([$refund]);
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function invalidRefunds(): array
    {
        return [['charge', 'ch_wrong'], ['payment_intent', 'pi_wrong'], ['amount', 19999],
            ['currency', 'eur'], ['livemode', true], ['status', 'pending']];
    }

    public function test_refund_webhook_finishes_while_reconciler_holds_an_older_processing_snapshot(): void
    {
        $this->order->update(['stripe_payment_intent' => 'pi_f3', 'payment_status' => 'paid', 'status' => 'processing']);
        $this->api->responses['/v1/refunds'] = function (): array {
            $stale = $this->refund('pending');
            $this->webhook('refund.updated', $this->refund())->assertOk();

            return $this->page([$stale]);
        };
        $this->assertSame('refund_completed', $this->reconcile());
        $this->assertSame('completed', $this->order->fresh()->refund_status);
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':refund:completed')->count());
    }

    public function test_return_refund_webhook_finishes_before_stale_reconciler_processing_snapshot(): void
    {
        $return = $this->returnRequest();
        $this->order->update(['stripe_payment_intent' => 'pi_f3', 'payment_status' => 'paid', 'status' => 'delivered']);
        $this->api->responses['/v1/refunds'] = function () use ($return): array {
            $stale = $this->refund('pending', 10000, $return);
            $this->webhook('refund.updated', $this->refund('succeeded', 10000, $return))->assertOk();

            return $this->page([$stale]);
        };
        $this->reconcile();
        $this->assertSame('completed', $return->fresh()->refund_status);
        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame('delivered', $this->order->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'return:'.$return->id.':refund:completed')->count());
    }

    public function test_older_worker_cannot_overwrite_a_newer_operational_claim(): void
    {
        $this->api->responses['/v1/checkout/sessions/cs_f3'] = function (): array {
            Order::query()->whereKey($this->order->id)->update([
                'stripe_reconcile_next_at' => now()->addHours(1), 'stripe_reconcile_claim' => (string) Str::uuid(), 'stripe_reconcile_result' => 'newer_worker']);

            return $this->session;
        };
        $this->assertSame('newer_worker', $this->reconcile());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertTrue($this->order->fresh()->stripe_reconcile_next_at->gt(now()->addMinutes(30)));
    }

    public function test_local_item_shipping_or_fingerprint_tampering_is_rejected(): void
    {
        $this->order->items()->first()->update(['price' => 99]);
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_enabled_command_runs_the_reconciler(): void
    {
        config(['stripe_reconciliation.enabled' => true]);
        $this->artisan('stripe:reconcile')->assertSuccessful();
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertCount(4, $this->api->calls);
    }

    public function test_open_recovered_session_repairs_id_and_safe_url_without_creating_a_session(): void
    {
        $this->order->update(['stripe_session_id' => null]);
        $this->session = array_replace($this->session, ['status' => 'open', 'payment_status' => 'unpaid',
            'payment_intent' => null, 'url' => 'https://checkout.stripe.com/c/pay/cs_f3']);
        $this->remote();
        $this->assertSame('payment_waiting', $this->reconcile());
        $this->assertSame('cs_f3', $this->order->fresh()->stripe_session_id);
        $this->assertSame($this->session['url'], $this->attempt->fresh()->stripe_session_url);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(['get'], array_values(array_unique(array_column($this->api->calls, 'method'))));
    }

    public function test_recovered_checkout_url_cannot_redirect_to_an_unapproved_host(): void
    {
        $this->session = array_replace($this->session, ['status' => 'open', 'payment_status' => 'unpaid',
            'payment_intent' => null, 'url' => 'https://evil.example.test/payment']);
        $this->remote();
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertNull($this->attempt->fresh()->stripe_session_url);
    }

    public static function dispatchedRefundStates(): array
    {
        return [['shipped'], ['delivered']];
    }

    #[DataProvider('dispatchedRefundStates')]
    public function test_recovery_records_financial_refund_without_restoring_dispatched_goods(string $status): void
    {
        $this->order->update(['payment_status' => 'paid', 'status' => $status,
            'shipped_at' => now(), 'stripe_payment_intent' => 'pi_f3']);
        $this->charge['amount_refunded'] = 20000;
        $this->remote([$this->refund()]);
        $this->assertSame('manual_review', $this->reconcile());
        $marker = $this->order->fresh()->refunded_at;
        $this->due();
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
        $this->assertSame('completed', $this->order->fresh()->refund_status);
        $this->assertSame($status, $this->order->fresh()->status);
        $this->assertEquals($marker, $this->order->fresh()->refunded_at);
        $this->assertNotNull($marker);
        $this->assertNull($this->order->fresh()->stock_restored_at);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':refund:completed')->count());
        $this->assertSame(0, TransactionalEmail::where('kind', 'cancelled')->count());
    }

    public static function legacyDispatchTimestamps(): array
    {
        return [['shipped_at'], ['delivered_at']];
    }

    #[DataProvider('legacyDispatchTimestamps')]
    public function test_recovery_legacy_dispatch_cannot_be_followed_by_stock_releasing_cancel(string $timestamp): void
    {
        $this->order->update(['payment_status' => 'paid', 'status' => 'processing',
            $timestamp => now(), 'stripe_payment_intent' => 'pi_f3']);
        $this->charge['amount_refunded'] = 20000;
        $this->remote([$this->refund()]);
        foreach ([1, 2] as $replay) {
            $this->due();
            $this->assertSame('manual_review', $this->reconcile());
            $caught = null;
            try {
                app(\App\Services\OrderService::class)->cancel($this->order);
            } catch (\RuntimeException $exception) {
                $caught = $exception;
            }
            $this->assertNotNull($caught, 'Dispatch history must prevent reservation release.');
        }
        $this->assertSame('processing', $this->order->fresh()->status);
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertNull($this->order->fresh()->stock_restored_at);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':refund:completed')->count());
    }

    public function test_full_return_refund_preserves_delivered_order_and_does_not_double_restore(): void
    {
        $return = $this->returnRequest(200);
        $return->items()->first()->update(['quantity' => 2, 'line_refund_amount' => 200]);
        $this->order->update(['payment_status' => 'paid', 'status' => 'delivered', 'stripe_payment_intent' => 'pi_f3']);
        $this->charge['amount_refunded'] = 20000;
        $this->remote([$this->refund('succeeded', 20000, $return)]);
        $this->assertSame('return_refund_reconciled', $this->reconcile());
        $this->due();
        $this->reconcile();
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
        $this->assertSame('delivered', $this->order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        // An old full-order refund response cannot restore stock again after a Return completed it.
        $this->assertSame('manual_review', app(StripeStateApplier::class)->refund(StripeObject::constructFrom($this->refund('pending'))));
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame('delivered', $this->order->fresh()->status);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'return:'.$return->id.':refund:completed')->count());
    }

    public function test_refund_post_is_rejected_inside_transaction_before_dispatch(): void
    {
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3']);
        try {
            DB::transaction(fn () => app(StripeService::class)->refundPayment($this->order->fresh()));
            $this->fail('Refund POST under a transaction was allowed.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('DB transaction', $e->getMessage());
        }
        $this->assertSame([], $this->api->calls);
    }

    public function test_incomplete_refund_page_error_does_not_complete_charge_or_restore_stock(): void
    {
        $this->charge['amount_refunded'] = 20000;
        $this->remote();
        $this->api->responses['/v1/refunds'] = $this->page([], true);
        $this->assertSame('api_or_local_error', $this->reconcile());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_lost_refund_post_response_schedules_prompt_recovery_instead_of_weekly_wait(): void
    {
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3',
            'stripe_reconcile_next_at' => now()->addWeek()]);
        $this->api->responses['/v1/refunds'] = fn () => throw ApiConnectionException::factory('F3 lost refund reply.');
        try {
            app(StripeService::class)->refundPayment($this->order->fresh());
            $this->fail('Lost response was not propagated.');
        } catch (ApiConnectionException) {
            $this->assertNull($this->order->fresh()->stripe_refund_id);
        }
        $this->assertTrue($this->order->fresh()->stripe_reconcile_next_at->lte(now()));
        $this->charge['amount_refunded'] = 20000;
        $this->remote([$this->refund()]);
        $this->assertSame('refund_completed', $this->reconcile());
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
    }

    #[DataProvider('unsafeReturns')]
    public function test_recovery_rejects_unsafe_return_method_currency_status_or_owner(string $field, mixed $value): void
    {
        $return = $this->returnRequest();
        if ($field === 'user_id') {
            $value = User::factory()->create()->id;
        }
        $return->update([$field => $value]);
        $this->charge['amount_refunded'] = 10000;
        $this->remote([$this->refund('succeeded', 10000, $return)]);
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertNull($return->fresh()->stock_restored_at);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function unsafeReturns(): array
    {
        return [['refund_method', 'bank_transfer'], ['refund_currency', 'EUR'], ['status', 'requested'], ['user_id', null]];
    }

    #[DataProvider('unsafePositions')]
    public function test_unsafe_return_positions_never_restore_or_record_completed_receipt(string $case): void
    {
        $return = $this->returnRequest();
        $item = $return->items()->first();
        match ($case) {
            'empty' => $return->items()->delete(),
            'zero' => $item->update(['quantity' => 0]),
            'negative' => $item->update(['quantity' => -1]),
            'excess' => $item->update(['quantity' => 3, 'line_refund_amount' => 300]),
            'price' => $item->update(['unit_price' => 90]),
            'line' => $item->update(['line_refund_amount' => 90]),
            'amount' => $return->update(['refund_amount' => 150]),
            'missing_product' => DB::statement('PRAGMA foreign_keys = OFF') && $this->order->items()->first()->update(['product_id' => 999999]),
            'competing' => $this->returnRequest()->items()->first()->update(['quantity' => 2, 'line_refund_amount' => 200]),
            'competing_empty' => $this->returnRequest()->items()->delete(),
            'other_order' => $item->update(['order_item_id' => OrderItem::create([
                'order_id' => $this->otherOrder()->id, 'product_id' => $this->product->id,
                'product_name' => 'Other', 'price' => 100, 'quantity' => 2, 'total' => 200,
            ])->id]),
        };
        DB::statement('PRAGMA foreign_keys = ON');
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3']);
        $refund = $this->refund('succeeded', (int) round((float) $return->fresh()->refund_amount * 100), $return);
        $this->assertSame('manual_review', app(StripeStateApplier::class)->refund(StripeObject::constructFrom($refund)));
        $this->charge['amount_refunded'] = $refund['amount'];
        $this->remote([$refund]);
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame('received', $return->fresh()->status);
        $this->assertNull($return->fresh()->stock_restored_at);
        $this->assertNull($return->fresh()->stripe_refund_id);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function unsafePositions(): array
    {
        return array_map(fn ($case) => [$case], ['empty', 'zero', 'negative', 'excess', 'price', 'line',
            'amount', 'missing_product', 'competing', 'competing_empty', 'other_order']);
    }

    private function otherOrder(): Order
    {
        $order = $this->order->replicate();
        $order->order_number = 'F3-other';
        $order->stripe_session_id = null;
        $order->stripe_payment_intent = null;
        $order->save();

        return $order;
    }

    public function test_validated_partial_return_rolls_back_status_stock_and_receipt_together(): void
    {
        $return = $this->returnRequest();
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3']);
        DB::unprepared("CREATE TEMP TRIGGER fail_return_stock BEFORE UPDATE OF stock_quantity ON products BEGIN SELECT RAISE(ABORT, 'return rollback'); END");
        try {
            app(StripeStateApplier::class)->refund(StripeObject::constructFrom($this->refund('succeeded', 10000, $return)));
            $this->fail('Expected stock transaction rollback.');
        } catch (QueryException) {
            $this->assertSame(8, $this->product->fresh()->stock_quantity);
            $this->assertSame('received', $return->fresh()->status);
            $this->assertNull($return->fresh()->stripe_refund_id);
            $this->assertSame(0, TransactionalEmail::count());
        }
    }

    public function test_same_second_claim_replacement_preserves_new_worker_result_cursor_and_failures(): void
    {
        $this->travelTo(now()->startOfSecond());
        $claims = [];
        $leases = [];
        $this->api->responses['/v1/checkout/sessions/cs_f3'] = function () use (&$claims, &$leases) {
            $claims[] = $this->order->fresh()->stripe_reconcile_claim;
            $leases[] = $this->order->fresh()->stripe_reconcile_next_at->timestamp;
            $this->order->update(['stripe_reconcile_next_at' => null, 'stripe_reconcile_claim' => null]);
            $this->api->responses['/v1/checkout/sessions/cs_f3'] = function () use (&$claims, &$leases) {
                $claims[] = $this->order->fresh()->stripe_reconcile_claim;
                $leases[] = $this->order->fresh()->stripe_reconcile_next_at->timestamp;

                return $this->session;
            };
            app(StripeReconciler::class)->run();
            $this->order->refresh()->update(['stripe_reconcile_result' => 'new_worker',
                'stripe_reconcile_cursor' => ['new' => 'cursor'], 'stripe_reconcile_failures' => 7]);

            return $this->session;
        };
        app(StripeReconciler::class)->run();
        $this->assertCount(2, $claims);
        $this->assertNotSame($claims[0], $claims[1]);
        $this->assertSame($leases[0], $leases[1]);
        $this->assertSame('new_worker', $this->order->fresh()->stripe_reconcile_result);
        $this->assertSame(['new' => 'cursor'], $this->order->fresh()->stripe_reconcile_cursor);
        $this->assertSame(7, $this->order->fresh()->stripe_reconcile_failures);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':paid')->count());
        $this->travelBack();
    }

    public function test_expired_claim_is_replaced_but_unexpired_claim_is_not(): void
    {
        $old = (string) Str::uuid();
        $this->order->update(['stripe_reconcile_claim' => $old, 'stripe_reconcile_next_at' => now()->addMinute()]);
        $this->assertSame([], app(StripeReconciler::class)->run());
        $this->assertSame($old, $this->order->fresh()->stripe_reconcile_claim);
        $this->order->update(['stripe_reconcile_next_at' => now()->subSecond()]);
        $observed = null;
        $this->api->responses['/v1/checkout/sessions/cs_f3'] = function () use (&$observed) {
            $observed = $this->order->fresh()->stripe_reconcile_claim;

            return $this->session;
        };
        $this->assertSame('paid', $this->reconcile());
        $this->assertNotSame($old, $observed);
        $this->assertNull($this->order->fresh()->stripe_reconcile_claim);
    }

    #[DataProvider('asyncEvidence')]
    public function test_async_failure_needs_validated_event_evidence(string $evidence, string $expected): void
    {
        $this->session['payment_status'] = 'unpaid';
        $this->pi['status'] = 'requires_payment_method';
        $this->pi['amount_received'] = 0;
        if ($evidence === 'retryable') {
            $this->attempt->update(['stripe_started_at' => now()->subHours(2)]);
        }
        $event = ['id' => 'evt_async', 'object' => 'event', 'type' => 'checkout.session.async_payment_failed',
            'livemode' => false, 'created' => time(), 'data' => ['object' => $this->session]];
        if ($evidence === 'ambiguous') {
            $event['data']['object']['metadata']['request_hash'] = 'wrong';
        }
        $this->remote();
        $this->api->responses['/v1/events'] = $this->page(in_array($evidence, ['valid', 'ambiguous'], true) ? [$event] : []);
        if ($evidence === 'valid') {
            $this->definitiveAsyncFailure();
            $this->api->responses['/v1/events'] = $this->page([$event]);
        }
        $this->assertSame($expected, $this->reconcile());
        $this->assertSame($expected === 'async_failed' ? 'failed' : 'pending', $this->order->fresh()->payment_status);
        $this->assertSame($expected === 'async_failed' ? 10 : 8, $this->product->fresh()->stock_quantity);
        $this->assertSame($expected === 'async_failed' ? 1 : 0, TransactionalEmail::count());
        $this->assertSame(['get'], array_values(array_unique(array_column($this->api->calls, 'method'))));
        if ($expected === 'async_failed') {
            $this->webhook('checkout.session.async_payment_failed', $this->session)->assertOk();
            $this->due();
            $this->assertSame('async_failed', $this->reconcile());
            $this->assertSame(10, $this->product->fresh()->stock_quantity);
            $this->assertSame(1, TransactionalEmail::count());
            $paid = array_replace($this->session, ['payment_status' => 'paid']);
            $this->webhook('checkout.session.async_payment_succeeded', $paid)->assertOk();
            $this->assertSame('cancelled', $this->order->fresh()->status);
            $this->assertSame(10, $this->product->fresh()->stock_quantity);
            $this->assertSame(0, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':paid')->count());
        }
    }

    public static function asyncEvidence(): array
    {
        return [['retryable', 'payment_waiting'], ['absent', 'async_failure_manual_review'],
            ['ambiguous', 'manual_review'], ['valid', 'async_failed']];
    }

    #[DataProvider('cursorChanges')]
    public function test_cursor_cannot_be_reused_after_relevant_attempt_changes(string $change): void
    {
        config(['stripe_reconciliation.read_budget' => 4]);
        $this->order->update(['stripe_session_id' => null]);
        $this->api->responses['/v1/checkout/sessions'] = function () {
            $id = 'cs_unrelated_'.count($this->api->calls);

            return $this->page([array_replace($this->session, ['id' => $id, 'metadata' => []])], true);
        };
        $this->assertSame('scan_incomplete', $this->reconcile());
        $this->assertNotNull($this->order->fresh()->stripe_reconcile_cursor['context']);
        $count = count($this->api->calls);
        match ($change) {
            'start' => $this->attempt->update(['stripe_started_at' => now()->subHours(26)]),
            'request' => $this->attempt->update(['request_hash' => str_repeat('d', 64)]),
            'parameters' => $this->attempt->update(['stripe_parameters' => array_replace(
                $this->attempt->stripe_parameters, ['mode' => 'subscription'])]),
            'items' => $this->order->items()->first()->update(['quantity' => 3]),
            'owner' => $this->order->update(['user_id' => User::factory()->create()->id]),
            'pi' => $this->order->update(['stripe_payment_intent' => 'pi_other']),
            'mode' => config(['services.stripe.mode' => 'live', 'services.stripe.secret' => 'sk_live_f3_fake']),
        };
        $this->due();
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertCount($count, $this->api->calls);
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function cursorChanges(): array
    {
        return [['start'], ['request'], ['parameters'], ['items'], ['owner'], ['pi'], ['mode']];
    }

    public function test_changed_snapshot_during_get_is_revalidated_under_local_locks(): void
    {
        $this->api->responses['/v1/checkout/sessions/cs_f3'] = function () {
            $this->attempt->update(['request_hash' => str_repeat('d', 64)]);

            return $this->session;
        };
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_discovery_uniqueness_checkpoint_cannot_authorize_effects_in_a_later_run(): void
    {
        $this->order->update(['stripe_session_id' => null, 'stripe_reconcile_cursor' => [
            'phase' => 'snapshot', 'session_id' => 'cs_f3',
            'context' => StripeReconciler::snapshotContext($this->order->fresh(), $this->attempt->fresh()),
        ]]);
        $this->assertSame('manual_review', $this->reconcile());
        $this->assertSame([], $this->api->calls);
        $this->assertNull($this->order->fresh()->stripe_session_id);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    #[DataProvider('disabledValues')]
    public function test_command_and_scheduler_require_actual_boolean_true(mixed $value): void
    {
        config(['stripe_reconciliation.enabled' => $value]);
        $this->artisan('stripe:reconcile')->assertSuccessful();
        $this->assertSame([], $this->api->calls);
        $this->assertNull($this->order->fresh()->stripe_reconcile_claim);
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'stripe:reconcile'));
        $this->assertNotNull($event);
        $this->assertFalse($event->filtersPass(app()));
    }

    public static function disabledValues(): array
    {
        return [[null], [false], ['off'], ['false'], ['true'], ['unknown'], [1], [0]];
    }

    public function test_admin_refund_response_and_webhook_share_unsafe_return_guard(): void
    {
        $return = $this->returnRequest();
        $return->items()->delete();
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3',
            'stripe_reconcile_claim' => (string) Str::uuid(), 'stripe_reconcile_next_at' => now()->addWeek()]);
        $refund = $this->refund('succeeded', 10000, $return);
        $this->api->responses['/v1/refunds'] = function () use ($refund) {
            $this->assertNull($this->order->fresh()->stripe_reconcile_claim);

            return $refund;
        };
        $caught = null;
        try {
            app(StripeService::class)->refundReturn($return);
        } catch (\RuntimeException $exception) {
            $caught = $exception;
            $this->assertSame('manual_review', $this->order->fresh()->stripe_reconcile_result);
        }
        $this->assertNotNull($caught, 'Unsafe automatic Return application was accepted.');
        $this->webhook('refund.updated', $refund)->assertOk();
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame('received', $return->fresh()->status);
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertNull($return->fresh()->stripe_refund_id);
    }

    public function test_async_failure_webhook_before_reconciliation_and_paid_before_failure_are_safe(): void
    {
        $unpaid = array_replace($this->session, ['payment_status' => 'unpaid']);
        $this->session = $unpaid;
        $this->definitiveAsyncFailure();
        $this->webhook('checkout.session.async_payment_failed', $unpaid)->assertOk();
        $this->session = $unpaid;
        $this->pi['status'] = 'requires_payment_method';
        $this->pi['amount_received'] = 0;
        $this->remote();
        $event = ['id' => 'evt_async', 'object' => 'event', 'type' => 'checkout.session.async_payment_failed',
            'livemode' => false, 'created' => time(), 'data' => ['object' => $this->session]];
        $this->api->responses['/v1/events'] = $this->page([$event]);
        $this->assertSame('async_failed', $this->reconcile());
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::count());
        // A separate paid fixture demonstrates that an older failure proof cannot undo payment.
        $this->order->update(['payment_status' => 'paid', 'status' => 'processing']);
        $this->due();
        $this->assertSame('terminal_local_state', $this->reconcile());
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::count());
    }

    public function test_config_file_accepts_only_explicit_true_environment_value(): void
    {
        $key = 'STRIPE_RECONCILIATION_ENABLED';
        $server = $_SERVER[$key] ?? null;
        $env = $_ENV[$key] ?? null;
        $process = getenv($key);
        try {
            foreach ([null, 'off', 'unknown', 'false', '0', '1', 'yes', 'true'] as $value) {
                if ($value === null) {
                    unset($_SERVER[$key], $_ENV[$key]);
                    putenv($key);
                } else {
                    $_SERVER[$key] = $_ENV[$key] = $value;
                    putenv($key.'='.$value);
                }
                $configuration = require base_path('config/stripe_reconciliation.php');
                $this->assertSame($value === 'true', $configuration['enabled']);
            }
        } finally {
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }
            if ($env === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $env;
            }
            $process === false ? putenv($key) : putenv($key.'='.$process);
        }
    }

    public function test_f3_preacquires_product_locks_in_id_order_before_legacy_restoration(): void
    {
        $second = $this->product->replicate();
        $second->slug = 'f3-second';
        $second->sku = 'F3-second';
        $second->stock_quantity = 9;
        $second->save();
        $this->product->update(['stock_quantity' => 9]);
        $this->order->items()->first()->update(['product_id' => $second->id, 'quantity' => 1, 'total' => 100]);
        $this->order->items()->create(['product_id' => $this->product->id, 'product_name' => $this->product->name,
            'price' => 100, 'quantity' => 1, 'total' => 100]);
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3']);
        $ids = [];
        DB::listen(function ($query) use (&$ids) {
            if (str_starts_with($query->sql, 'select * from "products" where "products"."id" =')) {
                $ids[] = $query->bindings[0];
            }
        });
        $this->assertSame('refund_completed', app(StripeStateApplier::class)->refund(StripeObject::constructFrom($this->refund())));
        $this->assertSame([$this->product->id, $second->id], array_slice($ids, 0, 2));
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(10, $second->fresh()->stock_quantity);
    }

    public function test_stale_async_worker_cannot_cancel_after_new_worker_observes_processing(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->order->update(['stripe_payment_intent' => 'pi_f3']);
        $this->session['payment_status'] = 'unpaid';
        $this->pi['status'] = 'requires_payment_method';
        $this->pi['amount_received'] = 0;
        $this->remote();
        $failure = ['id' => 'evt_old_async', 'object' => 'event', 'livemode' => false,
            'type' => 'checkout.session.async_payment_failed', 'created' => now()->timestamp,
            'data' => ['object' => $this->session]];
        $claims = [];
        $expiries = [];
        $newer = null;
        $this->api->responses['/v1/events'] = function () use ($failure, &$claims, &$expiries, &$newer) {
            $claims[] = $this->order->fresh()->stripe_reconcile_claim;
            $expiries[] = $this->order->fresh()->stripe_reconcile_next_at->timestamp;
            $this->order->update(['stripe_reconcile_claim' => null, 'stripe_reconcile_next_at' => now()]);
            $this->pi['status'] = 'processing';
            $this->api->responses['/v1/payment_intents/pi_f3'] = function () use (&$claims, &$expiries) {
                $claims[] = $this->order->fresh()->stripe_reconcile_claim;
                $expiries[] = $this->order->fresh()->stripe_reconcile_next_at->timestamp;

                return $this->pi;
            };
            $this->assertSame(['payment_waiting' => 1], app(StripeReconciler::class)->run());
            $newer = $this->order->fresh()->getAttributes();
            $this->assertSame(8, $this->product->fresh()->stock_quantity);

            return $this->page([$failure]);
        };
        $this->assertSame(['claim_lost' => 1], app(StripeReconciler::class)->run());
        $this->assertNotSame($claims[0], $claims[1]);
        $this->assertSame($expiries[0], $expiries[1]);
        $this->assertSame($newer, $this->order->fresh()->getAttributes());
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame('payment_waiting', $this->order->fresh()->stripe_reconcile_result);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
        $this->travelBack();
    }

    #[DataProvider('unownedRecoveryEffects')]
    public function test_every_recovery_effect_is_fenced_by_current_claim_and_unexpired_lease(string $effect, string $loss): void
    {
        $this->travelTo(now()->startOfSecond());
        $return = null;
        $refunds = [];
        if ($effect === 'expired') {
            $this->session = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        } elseif ($effect === 'canceled' || $effect === 'async') {
            $this->session['payment_status'] = 'unpaid';
            $this->pi['status'] = $effect === 'async' ? 'requires_payment_method' : 'canceled';
            $this->pi['amount_received'] = 0;
        } elseif ($effect === 'session_binding') {
            $this->order->update(['stripe_session_id' => null]);
            $this->session = array_replace($this->session, ['status' => 'open', 'payment_status' => 'unpaid',
                'payment_intent' => null, 'url' => 'https://checkout.stripe.com/c/pay/cs_f3']);
        } elseif ($effect === 'order_refund' || $effect === 'return_refund') {
            DB::table('orders')->where('id', $this->order->id)->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3']);
            $return = $effect === 'return_refund' ? $this->returnRequest() : null;
            $amount = $return ? 10000 : 20000;
            $this->charge['amount_refunded'] = $amount;
            $refunds = [$this->refund('succeeded', $amount, $return)];
        } elseif ($effect === 'late_payment') {
            $this->order->update(['status' => 'cancelled', 'payment_status' => 'failed', 'stock_restored_at' => now()]);
            $this->product->update(['stock_quantity' => 10]);
        }
        $this->remote($refunds);
        $this->api->responses['/v1/events'] = $this->page([[
            'id' => 'evt_async', 'object' => 'event', 'livemode' => false,
            'type' => 'checkout.session.async_payment_failed', 'created' => now()->timestamp,
            'data' => ['object' => $this->session],
        ]]);
        $lastPath = match ($effect) {
            'expired', 'session_binding' => '/v1/checkout/sessions/cs_f3',
            'canceled' => '/v1/payment_intents/pi_f3',
            'async' => '/v1/events',
            default => '/v1/refunds',
        };
        $response = $this->api->responses[$lastPath];
        $beforeBusiness = $this->order->fresh()->getAttributes();
        $beforeUrl = $this->attempt->fresh()->stripe_session_url;
        $beforeReturn = $return?->fresh()->getAttributes();
        $beforeStock = $this->product->fresh()->stock_quantity;
        $beforeEmails = TransactionalEmail::count();
        $afterInvalidation = null;
        $this->api->responses[$lastPath] = function () use ($response, $loss, &$afterInvalidation) {
            if ($loss === 'expired' || $loss === 'boundary') {
                $this->travel($loss === 'boundary' ? 240 : 241)->seconds();
            } else {
                $this->order->update(['stripe_reconcile_claim' => $loss === 'null' ? null : (string) Str::uuid(),
                    'stripe_reconcile_result' => 'newer_observation', 'stripe_reconcile_cursor' => ['new' => 'cursor'],
                    'stripe_reconcile_failures' => 5, 'stripe_reconcile_next_at' => now()->addHour()]);
            }
            $afterInvalidation = $this->order->fresh()->getAttributes();

            return $response;
        };
        $result = in_array($loss, ['expired', 'boundary'], true) ? 'lease_expired' : 'claim_lost';
        $this->assertSame([$result => 1], app(StripeReconciler::class)->run());
        $this->assertSame($afterInvalidation, $this->order->fresh()->getAttributes());
        foreach (['stripe_session_id', 'stripe_payment_intent', 'payment_status', 'status', 'stripe_refund_id',
            'refund_status', 'stock_restored_at', 'late_stripe_payment_at', 'refunded_at'] as $field) {
            $this->assertSame($beforeBusiness[$field] ?? null, $this->order->fresh()->getAttributes()[$field] ?? null, $field);
        }
        $this->assertSame($beforeUrl, $this->attempt->fresh()->stripe_session_url);
        $this->assertSame($beforeReturn, $return?->fresh()->getAttributes());
        $this->assertSame($beforeStock, $this->product->fresh()->stock_quantity);
        $this->assertSame($beforeEmails, TransactionalEmail::count());
        $this->travelBack();
    }

    public static function unownedRecoveryEffects(): array
    {
        $cases = [];
        foreach (['paid', 'expired', 'canceled', 'async', 'session_binding', 'pi_binding',
            'order_refund', 'return_refund', 'late_payment'] as $effect) {
            foreach (['new_uuid', 'null', 'expired', 'boundary'] as $loss) {
                $cases[] = [$effect, $loss];
            }
        }

        return $cases;
    }

    public function test_expired_worker_error_cannot_extend_lease_or_save_operational_result(): void
    {
        $before = null;
        $this->api->responses['/v1/checkout/sessions/cs_f3'] = function () use (&$before) {
            $before = $this->order->fresh()->getAttributes();
            $this->travel(241)->seconds();

            throw ApiConnectionException::factory('Fake expired request failure');
        };
        $this->assertSame(['completion_not_owned' => 1], app(StripeReconciler::class)->run());
        $this->assertSame($before, $this->order->fresh()->getAttributes());
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->travelBack();
    }

    public function test_webhook_and_admin_refund_continue_without_a_reconciliation_claim(): void
    {
        $this->webhook('checkout.session.completed', $this->session)->assertOk();
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertNull($this->order->fresh()->stripe_reconcile_claim);
        $return = $this->returnRequest();
        $this->order->update(['stripe_reconcile_claim' => (string) Str::uuid(), 'stripe_reconcile_next_at' => now()->addHour()]);
        $this->api->responses['/v1/refunds'] = function () use ($return) {
            $this->assertNull($this->order->fresh()->stripe_reconcile_claim);

            return $this->refund('succeeded', 10000, $return);
        };
        app(StripeService::class)->refundReturn($return);
        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'return:'.$return->id.':refund:completed')->count());
    }

    #[DataProvider('terminalObservations')]
    public function test_operational_failure_result_describes_current_terminal_state(string $state, string $external): void
    {
        DB::table('orders')->where('id', $this->order->id)->update(['payment_status' => $state, 'stripe_payment_intent' => 'pi_f3']);
        $this->session['payment_status'] = 'unpaid';
        $this->session['status'] = $external === 'expired' ? 'expired' : 'complete';
        $this->pi['status'] = $external === 'async' ? 'requires_payment_method' : 'canceled';
        $this->pi['amount_received'] = 0;
        $this->remote();
        $this->api->responses['/v1/events'] = $this->page([[
            'id' => 'evt_async', 'object' => 'event', 'livemode' => false,
            'type' => 'checkout.session.async_payment_failed', 'created' => now()->timestamp,
            'data' => ['object' => $this->session],
        ]]);
        $this->assertSame('terminal_local_state', $this->reconcile());
        $this->assertSame($state, $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public static function terminalObservations(): array
    {
        return [['paid', 'expired'], ['paid', 'canceled'], ['paid', 'async'],
            ['refunded', 'expired'], ['refunded', 'canceled'], ['refunded', 'async']];
    }

    public function test_webhook_payment_during_get_preserves_paid_under_a_still_valid_claim(): void
    {
        $this->order->update(['stripe_payment_intent' => 'pi_f3']);
        $paidSession = $this->session;
        $this->session['payment_status'] = 'unpaid';
        $this->pi['status'] = 'canceled';
        $this->pi['amount_received'] = 0;
        $this->remote();
        $claim = null;
        $this->api->responses['/v1/payment_intents/pi_f3'] = function () use ($paidSession, &$claim) {
            $claim = $this->order->fresh()->stripe_reconcile_claim;
            $this->webhook('checkout.session.completed', $paidSession)->assertOk();
            $this->assertSame($claim, $this->order->fresh()->stripe_reconcile_claim);

            return $this->pi;
        };
        $this->assertSame('terminal_local_state', $this->reconcile());
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame('processing', $this->order->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':paid')->count());
        $this->assertSame(0, TransactionalEmail::where('event_key', 'order:'.$this->order->id.':cancelled')->count());
    }

    #[DataProvider('webhookContexts')]
    public function test_webhook_and_recovery_share_strict_live_test_context(bool $expected, string $kind, string $context): void
    {
        config(['services.stripe.mode' => $expected ? 'live' : 'test', 'services.stripe.secret' => $expected ? 'sk_live_f3_fake' : 'sk_test_f3_fake']);
        $return = $kind === 'return_refund' ? $this->returnRequest() : null;
        if (str_contains($kind, 'refund')) {
            DB::table('orders')->where('id', $this->order->id)->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_f3']);
            $object = $this->refund('succeeded', $return ? 10000 : 20000, $return);
            $type = 'refund.updated';
        } else {
            $object = $this->session;
            $type = $kind === 'async' ? 'checkout.session.async_payment_failed' : 'checkout.session.completed';
            if ($kind === 'async') {
                $object['payment_status'] = 'unpaid';
                $this->session = $object;
                $this->session['livemode'] = $expected;
                $this->pi['livemode'] = $expected;
                $this->charge['livemode'] = $expected;
                $this->definitiveAsyncFailure();
            }
        }
        $object['livemode'] = $context === 'match' ? $expected : ! $expected;
        if ($context === 'missing') {
            unset($object['livemode']);
        } elseif ($context === 'null') {
            $object['livemode'] = null;
        }
        $response = $this->webhook($type, $object)->assertOk();
        if ($context !== 'match') {
            $response->assertJson(['ignored' => 'incompatible_context']);
            $this->assertSame(8, $this->product->fresh()->stock_quantity);
            $this->assertSame(str_contains($kind, 'refund') ? 'paid' : 'pending', $this->order->fresh()->payment_status);
            $this->assertSame(0, TransactionalEmail::count());
            $this->assertSame($return ? 'received' : null, $return?->fresh()->status);
        } else {
            $this->assertSame($kind === 'payment' ? 8 : ($return ? 9 : 10), $this->product->fresh()->stock_quantity);
            $this->assertSame($return ? 'refunded' : null, $return?->fresh()->status);
            $this->assertGreaterThan(0, TransactionalEmail::count());
        }
    }

    public static function webhookContexts(): array
    {
        $cases = [];
        foreach ([false, true] as $expected) {
            foreach (['payment', 'async', 'order_refund', 'return_refund'] as $kind) {
                foreach (['match', 'mismatch', 'missing', 'null'] as $context) {
                    $cases[] = [$expected, $kind, $context];
                }
            }
        }

        return $cases;
    }

    public function test_webhook_envelope_context_is_required_even_when_object_matches(): void
    {
        foreach ([['livemode' => true], ['livemode' => null]] as $envelope) {
            $this->webhook('checkout.session.completed', $this->session, $envelope)
                ->assertOk()->assertJson(['ignored' => 'incompatible_context']);
        }
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertNull($this->order->fresh()->stripe_payment_intent);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_incompatible_webhook_logs_only_safe_event_identity(): void
    {
        Log::spy();
        $this->webhook('refund.updated', array_replace($this->refund(), ['livemode' => true]))
            ->assertOk()->assertJson(['ignored' => 'incompatible_context']);
        Log::shouldHaveReceived('warning')->once()->with(
            'Stripe webhook context rejected.', ['event_id' => 'evt_f3', 'event_type' => 'refund.updated']);
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
    }

    public function test_webhook_signature_is_verified_before_trust_context(): void
    {
        $payload = json_encode(['id' => 'evt_fake', 'object' => 'event', 'livemode' => false,
            'type' => 'checkout.session.completed', 'data' => ['object' => $this->session]], JSON_THROW_ON_ERROR);
        $this->call('POST', route('stripe.webhook'), [], [], [],
            ['HTTP_Stripe-Signature' => 't='.time().',v1='.str_repeat('0', 64)], $payload)
            ->assertStatus(400)->assertJson(['message' => 'Invalid signature.']);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_common_refund_context_guard_also_protects_admin_application(): void
    {
        $return = $this->returnRequest();
        $this->order->update(['stripe_payment_intent' => 'pi_f3']);
        $refund = $this->refund('succeeded', 10000, $return);
        $refund['livemode'] = true;
        $this->assertSame('manual_review', app(StripeStateApplier::class)->refund(StripeObject::constructFrom($refund)));
        $this->api->responses['/v1/refunds'] = $refund;
        $caught = null;
        try {
            app(StripeService::class)->refundReturn($return);
        } catch (\RuntimeException $e) {
            $caught = $e;
            $this->assertStringContainsString('manual review', $e->getMessage());
        }
        $this->assertNotNull($caught, 'Mismatched admin response applied business effects.');
        $this->assertSame('received', $return->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    private function webhook(string $type, array $object, array $envelope = [])
    {
        $payload = json_encode(array_replace(['id' => 'evt_f3', 'object' => 'event',
            'livemode' => app(StripeModePolicy::class)->livemode(), 'created' => time(), 'type' => $type, 'data' => ['object' => $object]], $envelope), JSON_THROW_ON_ERROR);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$payload, 'whsec_f3_fake');

        return $this->call('POST', route('stripe.webhook'), [], [], [],
            ['HTTP_Stripe-Signature' => "t={$time},v1={$signature}"], $payload);
    }
}

class ReconciliationStripeClient implements ClientInterface
{
    public array $responses = [];

    public array $queues = [];

    public array $errors = [];

    public array $calls = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $level = DB::transactionLevel();
        if ($level !== 0) {
            throw new \LogicException('HTTP ran under DB transaction.');
        }
        $this->calls[] = ['method' => $method, 'url' => $absUrl, 'transaction' => $level, 'params' => $params];
        $path = parse_url($absUrl, PHP_URL_PATH);
        if (isset($this->errors[$path])) {
            $code = $this->errors[$path];
            if ($code === 0) {
                throw ApiConnectionException::factory('F3 simulated timeout.');
            }

            return [json_encode(['error' => ['type' => 'api_error', 'message' => 'F3 simulated failure']]), $code, []];
        }
        if (! empty($this->queues[$path])) {
            $response = array_shift($this->queues[$path]);
        } else {
            $response = $this->responses[$path] ?? throw new \LogicException('Unmocked Stripe request: '.$path);
        }
        if (is_callable($response)) {
            $response = $response($params);
        }

        return [json_encode($response, JSON_THROW_ON_ERROR), 200, []];
    }
}
