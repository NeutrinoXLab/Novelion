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
use App\Services\StripeRecoveryValidator;
use App\Services\StripeService;
use App\Services\StripeStateApplier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeObject;
use Tests\Concerns\UsesCommittedDatabase;
use Tests\TestCase;

class StripeRemediationFourTest extends TestCase
{
    use UsesCommittedDatabase;

    private Order $order;

    private Product $product;

    private CheckoutAttempt $attempt;

    private RemediationFourClient $api;

    private array $session;

    private array $pi;

    private array $charge;

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.default' => 'null', 'services.stripe.mode' => 'test', 'services.stripe.secret' => 'sk_test_r4_fake', 'services.stripe.webhook_secret' => 'whsec_r4_fake']);
        $user = User::factory()->create();
        $category = Category::create(['name' => 'R4', 'slug' => 'r4']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'R4', 'slug' => 'r4', 'sku' => 'R4', 'selling_price' => 100, 'purchase_price' => 50, 'stock_quantity' => 8]);
        $this->order = Order::create(['user_id' => $user->id, 'order_number' => 'R4-1', 'first_name' => 'R4', 'last_name' => 'Test', 'email' => 'r4@example.test', 'phone' => '0700000000', 'county' => 'Test', 'city' => 'Test', 'address' => 'Test', 'subtotal' => 200, 'shipping_cost' => 0, 'total' => 200, 'payment_method' => 'stripe', 'payment_status' => 'pending', 'status' => 'pending', 'stripe_session_id' => 'cs_r4']);
        OrderItem::create(['order_id' => $this->order->id, 'product_id' => $this->product->id, 'product_name' => 'R4', 'price' => 100, 'quantity' => 2, 'total' => 200]);
        $this->attempt = CheckoutAttempt::create(['token' => (string) Str::uuid(), 'user_id' => $user->id, 'order_id' => $this->order->id, 'session_hash' => str_repeat('a', 64), 'cart_hash' => str_repeat('b', 64), 'request_hash' => str_repeat('c', 64), 'expires_at' => now()->addHour(), 'stripe_started_at' => now()->subHour()]);
        $params = app(StripeService::class)->checkoutParameters($this->order);
        $params['client_reference_id'] = $this->attempt->token;
        $params['metadata'] = ['order_id' => (string) $this->order->id, 'user_id' => (string) $user->id, 'checkout_attempt' => $this->attempt->token, 'request_hash' => $this->attempt->request_hash];
        $params['metadata']['checkout_fingerprint'] = CheckoutAttempts::stripeFingerprint($params);
        $this->attempt->update(['stripe_parameters' => $params]);
        $this->session = ['id' => 'cs_r4', 'object' => 'checkout.session', 'livemode' => false, 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid', 'currency' => 'ron', 'amount_total' => 20000, 'payment_intent' => 'pi_r4', 'client_reference_id' => $this->attempt->token, 'metadata' => $params['metadata']];
        $this->pi = ['id' => 'pi_r4', 'object' => 'payment_intent', 'livemode' => false, 'amount' => 20000, 'amount_received' => 20000, 'currency' => 'ron', 'status' => 'succeeded', 'latest_charge' => 'ch_r4'];
        $this->charge = ['id' => 'ch_r4', 'object' => 'charge', 'livemode' => false, 'amount' => 20000, 'amount_refunded' => 0, 'currency' => 'ron', 'payment_intent' => 'pi_r4', 'paid' => true, 'status' => 'succeeded', 'created' => time() - 600];
        $this->api = new RemediationFourClient;
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
        $this->api->responses = ['/v1/checkout/sessions/cs_r4' => $this->session, '/v1/payment_intents/pi_r4' => $this->pi, '/v1/charges/ch_r4' => $this->charge, '/v1/refunds' => $this->page($refunds), '/v1/events' => $this->page(), '/v1/checkout/sessions' => $this->page([$this->session])];
    }

    private function failed(): void
    {
        $this->session['payment_status'] = 'unpaid';
        $this->pi['status'] = 'requires_payment_method';
        $this->pi['amount_received'] = 0;
        $this->pi['last_payment_error'] = ['charge' => 'ch_r4'];
        $this->charge['status'] = 'failed';
        $this->charge['paid'] = false;
        $this->remote();
    }

    private function event(?array $object = null, ?int $created = null, string $type = 'checkout.session.async_payment_failed'): array
    {
        return ['id' => 'evt_r4', 'object' => 'event', 'livemode' => $object['livemode'] ?? false, 'type' => $type, 'created' => $created ?? time() - 5, 'data' => ['object' => $object ?? $this->session]];
    }

    private function webhook(array $event, bool $validSignature = true)
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$payload, $validSignature ? 'whsec_r4_fake' : 'whsec_wrong');

        return $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_Stripe-Signature' => "t=$time,v1=$signature"], $payload);
    }

    private function due(): void
    {
        DB::table('orders')->where('id', $this->order->id)->update(['stripe_reconcile_next_at' => null]);
    }

    private function refund(?ReturnRequest $return = null, string $status = 'succeeded'): array
    {
        return ['id' => 're_r4', 'object' => 'refund', 'livemode' => false, 'payment_intent' => 'pi_r4', 'charge' => 'ch_r4', 'currency' => 'ron', 'amount' => $return ? 10000 : 20000, 'status' => $status, 'metadata' => ['order_id' => (string) $this->order->id] + ($return ? ['return_request_id' => (string) $return->id] : [])];
    }

    private function returnRequest(): ReturnRequest
    {
        $return = ReturnRequest::create(['order_id' => $this->order->id, 'user_id' => $this->order->user_id, 'reason' => 'R4', 'status' => 'received', 'requested_at' => now(), 'refund_amount' => 100, 'refund_currency' => 'RON', 'refund_method' => 'stripe']);
        $return->items()->create(['order_item_id' => $this->order->items()->first()->id, 'quantity' => 1, 'unit_price' => 100, 'line_refund_amount' => 100]);

        return $return;
    }

    private function runAgain(): string
    {
        $this->due();
        // New service instance models the absence of in-process provenance on every retry.
        app(StripeReconciler::class)->run();

        return $this->order->fresh()->stripe_reconcile_result;
    }

    private function untouched(): void
    {
        $order = $this->order->fresh();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertNull($order->stripe_session_id);
        $this->assertNull($order->stripe_payment_intent);
        $this->assertSame(0, TransactionalEmail::count());
    }

    private function sessions(array &$objects, ?int &$errorAt, bool $honorLimit = false): void
    {
        $call = 0;
        $this->api->responses['/v1/checkout/sessions'] = function ($params) use (&$objects, &$errorAt, &$call, $honorLimit) {
            if (++$call === $errorAt) {
                throw new \RuntimeException('R4 list injection');
            }
            $ids = array_column($objects, 'id');
            $start = isset($params['starting_after']) ? array_search($params['starting_after'], $ids, true) + 1 : 0;

            $limit = $honorLimit ? $params['limit'] : 1;

            return $this->page(array_slice($objects, $start, $limit), $start + $limit < count($objects));
        };
    }

    #[DataProvider('discoveryCases')]
    public function test_discovery_exception_revalidates_the_entire_window(int $errorAt, string $mutation): void
    {
        config(['stripe_reconciliation.page_size' => 1]);
        $this->order->update(['stripe_session_id' => null]);
        $paid = $this->session;
        $paid['id'] = 'cs_paid_other';
        $paid['metadata'] = [];
        $expired = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        $objects = [$paid, array_replace($paid, ['id' => 'cs_noise']), $expired];
        $this->api->responses['/v1/checkout/sessions/cs_r4'] = $expired;
        $this->sessions($objects, $errorAt);
        $this->assertSame('api_or_local_error', $this->runAgain());
        $this->untouched();
        $this->assertNotNull($this->order->fresh()->stripe_reconcile_cursor);
        $errorAt = null;
        if ($mutation === 'insert') {
            array_unshift($objects, array_replace($this->session, ['id' => 'cs_inserted']));
        } elseif ($mutation === 'reorder') {
            $objects = array_reverse($objects);
            $objects[2]['metadata'] = $this->session['metadata'];
        } else {
            $objects[0]['metadata'] = $this->session['metadata'];
        }
        $this->assertSame('manual_review', $this->runAgain());
        $this->untouched();
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
    }

    public static function discoveryCases(): array
    {
        $cases = [];
        foreach ([1, 2, 3] as $page) {
            foreach (['metadata', 'insert', 'reorder'] as $mutation) {
                $cases[] = [$page, $mutation];
            }
        }

        return $cases;
    }

    #[DataProvider('snapshotPasses')]
    public function test_snapshot_get_exception_does_not_preserve_uniqueness_authority(bool $secondPass): void
    {
        config(['stripe_reconciliation.page_size' => 1]);
        $this->order->update(['stripe_session_id' => null]);
        $other = array_replace($this->session, ['id' => 'cs_paid_other', 'metadata' => []]);
        $expired = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        $objects = [$other, $expired];
        $errorAt = null;
        $this->sessions($objects, $errorAt);
        if ($secondPass) {
            $objects = [$other, array_replace($other, ['id' => 'cs_noise_1']), array_replace($other, ['id' => 'cs_noise_2']), array_replace($other, ['id' => 'cs_noise_3']), $expired];
            config(['stripe_reconciliation.read_budget' => 4]);
            $this->assertSame('scan_incomplete', $this->runAgain());
            config(['stripe_reconciliation.read_budget' => 100]);
        }
        $this->api->responses['/v1/checkout/sessions/cs_r4'] = fn () => throw new \RuntimeException('Fresh GET');
        $this->assertSame('api_or_local_error', $this->runAgain());
        $this->assertSame('snapshot', $this->order->fresh()->stripe_reconcile_cursor['phase']);
        $objects[0]['metadata'] = $this->session['metadata'];
        $this->api->responses['/v1/checkout/sessions/cs_r4'] = $expired;
        $this->assertSame('manual_review', $this->runAgain());
        $this->untouched();
    }

    public static function snapshotPasses(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('additionalDiscoveryChanges')]
    public function test_fresh_discovery_and_get_control_changes_after_api_error(string $change): void
    {
        config(['stripe_reconciliation.page_size' => 1]);
        $this->order->update(['stripe_session_id' => null]);
        $expired = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        $other = array_replace($this->session, ['id' => 'cs_other']);
        $objects = [$change === 'lost_correlation' ? $other : array_replace($other, ['metadata' => []]), $expired];
        $error = 2;
        $this->sessions($objects, $error);
        $this->api->responses['/v1/checkout/sessions/cs_r4'] = $expired;
        $this->assertSame('api_or_local_error', $this->runAgain());
        $this->untouched();
        $error = null;
        if ($change === 'lost_correlation') {
            $objects[0]['metadata'] = [];
        }
        if ($change === 'new_expired') {
            array_unshift($objects, array_replace($expired, ['id' => 'cs_inserted_expired']));
        }
        if ($change === 'status_paid') {
            $objects[1] = $this->session;
            $this->api->responses['/v1/checkout/sessions/cs_r4'] = $this->session;
        }
        $result = $this->runAgain();
        if ($change === 'status_paid') {
            $this->assertSame('paid', $result);
            $this->assertSame('paid', $this->order->fresh()->payment_status);
            $this->assertSame(8, $this->product->fresh()->stock_quantity);
            $this->assertSame(1, TransactionalEmail::where('kind', 'paid')->count());
            $this->assertSame(0, TransactionalEmail::where('kind', 'cancelled')->count());
        } else {
            $this->assertSame('manual_review', $result);
            $this->untouched();
        }
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
    }

    public static function additionalDiscoveryChanges(): array
    {
        return [['lost_correlation'], ['new_expired'], ['status_paid']];
    }

    #[DataProvider('invalidCursors')]
    public function test_invalid_cursor_is_cleared_once_without_http_or_effects(mixed $value): void
    {
        DB::table('orders')->where('id', $this->order->id)->update(['stripe_reconcile_cursor' => json_encode($value)]);
        $this->assertSame('manual_review', $this->runAgain());
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertSame([], $this->api->calls);
        $this->assertSame('paid', $this->runAgain());
    }

    public static function invalidCursors(): array
    {
        $base = ['version' => 3, 'context' => str_repeat('a', 64), 'phase' => 'snapshot', 'session_id' => 'cs_r4', 'started_at' => time(), 'pages' => 0];
        $cases = [['scalar'], [123], [true], [[]], [(object) []]];
        foreach (['version' => 2, 'phase' => 'unknown', 'pages' => -1, 'started_at' => time() + 3600, 'session_id' => 'bad'] as $key => $value) {
            $cases[] = [array_replace($base, [$key => $value])];
        }
        foreach (['pages', 'started_at', 'session_id'] as $key) {
            $missing = $base;
            unset($missing[$key]);
            $cases[] = [$missing];
        }
        $cases[] = [array_replace($base, ['pages' => 1001])];
        $cases[] = [array_replace($base, ['started_at' => time() - 100000])];
        $cases[] = [array_replace($base, ['phase' => 'async_proof'])];
        foreach ([['version' => 99], ['pages' => '0'], ['pages' => 0.5], ['resumed' => null], ['scan_type' => 'wrong'], ['extra' => 'unsupported'], ['discovery_proof' => null], ['session_id' => 'pi_wrong']] as $change) {
            $cases[] = [array_replace($base, $change)];
        }

        return $cases;
    }

    #[DataProvider('refundErrors')]
    public function test_refund_resume_never_selects_return_from_cached_metadata(string $error, string $mutation): void
    {
        config(['stripe_reconciliation.page_size' => 1]);
        // Two distinct products and Returns; a stale association must never restore A.
        $this->order->items()->first()->update(['quantity' => 1, 'total' => 100]);
        $productB = Product::create(['category_id' => $this->product->category_id, 'name' => 'B', 'slug' => 'b', 'sku' => 'B', 'selling_price' => 100, 'purchase_price' => 50, 'stock_quantity' => 8]);
        $itemB = OrderItem::create(['order_id' => $this->order->id, 'product_id' => $productB->id, 'product_name' => 'B', 'price' => 100, 'quantity' => 1, 'total' => 100]);
        $this->order->refresh();
        $params = app(StripeService::class)->checkoutParameters($this->order);
        $params['client_reference_id'] = $this->attempt->token;
        $params['metadata'] = $this->session['metadata'];
        unset($params['metadata']['checkout_fingerprint']);
        $params['metadata']['checkout_fingerprint'] = CheckoutAttempts::stripeFingerprint($params);
        $this->attempt->update(['stripe_parameters' => $params]);
        $this->session['metadata'] = $params['metadata'];
        $this->assertCount(2, $params['line_items']);
        $this->assertSame(2, $this->order->items()->count());
        $this->assertTrue(app(StripeRecoveryValidator::class)->session($this->order->fresh(), $this->attempt->fresh(), StripeObject::constructFrom($this->session), StripeObject::constructFrom($this->pi)));
        $a = $this->returnRequest();
        $b = $this->returnRequest();
        $b->items()->delete();
        $b->items()->create(['order_item_id' => $itemB->id, 'quantity' => 1, 'unit_price' => 100, 'line_refund_amount' => 100]);
        $this->order->update(['payment_status' => 'paid', 'stripe_payment_intent' => 'pi_r4']);
        $this->charge['amount_refunded'] = 10000;
        $success = $this->refund($a);
        $tail = array_replace($this->refund($b, 'failed'), ['id' => 're_tail']);
        $this->assertTrue(app(StripeRecoveryValidator::class)->charge($this->order->fresh(), StripeObject::constructFrom($this->pi), StripeObject::constructFrom($this->charge), [StripeObject::constructFrom($success), StripeObject::constructFrom($tail)]));
        if ($mutation === 'status') {
            $success['status'] = 'pending';
        }
        $this->remote();
        $calls = 0;
        $throw = true;
        $this->api->responses['/v1/refunds'] = function ($params) use (&$success, $tail, &$calls, &$throw, $error, $mutation) {
            $calls++;
            if ($throw && (($error === 'first' && $calls === 1) || ($error === 'tail' && isset($params['starting_after'])) || ($error === 'head' && $calls === 3))) {
                throw new \RuntimeException('Refund page');
            }

            return isset($params['starting_after']) ? $this->page($mutation === 'positive' ? [] : [$tail]) : $this->page([$success], true);
        };
        $this->api->responses['/v1/refunds/re_r4'] = function () use (&$success, &$throw, $error) {
            if ($throw && $error === 'retrieve') {
                throw new \RuntimeException('Refund retrieve');
            }

            return $success;
        };
        $this->api->responses['/v1/refunds/re_tail'] = $tail;
        // For retrieve/head failures, enter refresh using a real partial run first.
        if (in_array($error, ['retrieve', 'head', 'session', 'pi', 'charge'], true)) {
            config(['stripe_reconciliation.read_budget' => 4]);
            $this->assertSame('scan_incomplete', $this->runAgain());
            config(['stripe_reconciliation.read_budget' => 100]);
        }
        $failurePath = ['session' => '/v1/checkout/sessions/cs_r4', 'pi' => '/v1/payment_intents/pi_r4', 'charge' => '/v1/charges/ch_r4'][$error] ?? null;
        if ($failurePath !== null) {
            $this->api->responses[$failurePath] = fn () => throw new \RuntimeException('Fresh validation');
        }
        $this->assertSame('api_or_local_error', $this->runAgain());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(8, $productB->fresh()->stock_quantity);
        $success['metadata']['return_request_id'] = (string) $b->id;
        $throw = false;
        if ($failurePath !== null) {
            $this->api->responses[$failurePath] = ['session' => $this->session, 'pi' => $this->pi, 'charge' => $this->charge][$error];
        }
        if ($mutation === 'status') {
            $success['status'] = 'succeeded';
        }
        if ($mutation === 'amount') {
            $success['amount'] = 9999;
        }
        if ($mutation === 'currency') {
            $success['currency'] = 'eur';
        }
        if ($mutation === 'return_state') {
            $b->update(['status' => 'refunded', 'refund_status' => 'completed']);
        }
        if ($mutation === 'order_state') {
            $this->order->update(['payment_status' => 'refunded']);
        }
        if ($mutation === 'insert' || $mutation === 'reorder') {
            $this->api->responses['/v1/refunds/re_inserted'] = array_replace($success, ['id' => 're_inserted']);
            $this->api->responses['/v1/refunds'] = $mutation === 'insert'
                ? $this->page([array_replace($success, ['id' => 're_inserted']), $success, $tail])
                : $this->page([$tail, $success]);
        }
        $this->runAgain();
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertContains($productB->fresh()->stock_quantity, [8, 9]);
        $this->assertSame('received', $a->fresh()->status);
        $this->assertNotSame('completed', $a->fresh()->refund_status);
        $this->assertNull($a->fresh()->stripe_refund_id);
        $this->assertSame(0, TransactionalEmail::where('event_key', 'return:'.$a->id.':refund:completed')->count());
        $this->assertLessThanOrEqual(1, TransactionalEmail::where('event_key', 'return:'.$b->id.':refund:completed')->count());
        $this->assertContains($this->order->fresh()->payment_status, ['paid', 'refunded']);
        $this->assertSame('cs_r4', $this->order->fresh()->stripe_session_id);
        $this->assertSame('pi_r4', $this->order->fresh()->stripe_payment_intent);
        if ($productB->fresh()->stock_quantity === 9) {
            $this->assertSame('refunded', $b->fresh()->status);
            $this->assertSame('completed', $b->fresh()->refund_status);
            $this->assertSame('re_r4', $b->fresh()->stripe_refund_id);
        }
        $this->assertContains($this->order->fresh()->stripe_reconcile_result, ['manual_review', 'return_refund_reconciled', 'terminal_local_state']);
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        if ($mutation === 'positive') {
            $this->assertSame('return_refund_reconciled', $this->order->fresh()->stripe_reconcile_result);
            $this->assertSame(9, $productB->fresh()->stock_quantity);
            $this->assertSame('completed', $b->fresh()->refund_status);
            $this->assertSame('re_r4', $b->fresh()->stripe_refund_id);
            $this->assertSame(1, TransactionalEmail::where('event_key', 'return:'.$b->id.':refund:completed')->count());
            $before = TransactionalEmail::count();
            $this->runAgain();
            $this->assertSame(8, $this->product->fresh()->stock_quantity);
            $this->assertSame(9, $productB->fresh()->stock_quantity);
            $this->assertSame($before, TransactionalEmail::count());
        }
    }

    public static function refundErrors(): array
    {
        $cases = [];
        foreach (['first', 'tail', 'retrieve', 'head', 'session', 'pi', 'charge'] as $error) {
            foreach (['association', 'positive', 'status', 'amount', 'currency', 'return_state', 'order_state', 'insert', 'reorder'] as $mutation) {
                $cases[] = [$error, $mutation];
            }
        }

        return $cases;
    }

    #[DataProvider('verificationErrors')]
    public function test_exception_after_reading_head_of_second_pass_cannot_hide_new_correlation(int $errorAt): void
    {
        config(['stripe_reconciliation.page_size' => 1, 'stripe_reconciliation.read_budget' => 4]);
        $this->order->update(['stripe_session_id' => null]);
        $objects = [];
        for ($i = 0; $i < 4; $i++) {
            $objects[] = array_replace($this->session, ['id' => 'cs_noise_'.$i, 'metadata' => []]);
        }
        $expired = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        $objects[] = $expired;
        $error = null;
        $this->sessions($objects, $error);
        $this->assertSame('scan_incomplete', $this->runAgain());
        config(['stripe_reconciliation.read_budget' => 100]);
        $error = $errorAt;
        $this->assertSame('api_or_local_error', $this->runAgain());
        $objects[0]['metadata'] = $this->session['metadata'];
        $error = null;
        $this->api->responses['/v1/checkout/sessions/cs_r4'] = $expired;
        $this->assertSame('manual_review', $this->runAgain());
        $this->untouched();
    }

    public static function verificationErrors(): array
    {
        // First/partial/last second-pass page, then first/partial/last current-run verification page.
        return [[6], [7], [10], [11], [13], [15]];
    }

    public function test_claim_loss_in_fresh_refund_revalidation_preserves_new_worker_cursor(): void
    {
        config(['stripe_reconciliation.read_budget' => 4]);
        $refund = $this->refund();
        $this->charge['amount_refunded'] = 20000;
        $this->remote([$refund]);
        $this->api->responses['/v1/refunds'] = fn ($params) => $this->page([$refund], ! isset($params['starting_after']));
        $this->assertSame('scan_incomplete', $this->runAgain());
        $this->api->responses['/v1/refunds'] = fn ($params) => isset($params['starting_after']) ? $this->page() : $this->page([$refund]);
        $this->api->responses['/v1/refunds/re_r4'] = function () use ($refund) {
            DB::table('orders')->where('id', $this->order->id)->update(['stripe_reconcile_claim' => 'worker-b', 'stripe_reconcile_next_at' => now()->addHour(), 'stripe_reconcile_result' => 'payment_waiting', 'stripe_reconcile_cursor' => json_encode(['B' => 'owned'])]);

            return $refund;
        };
        $this->due();
        $this->assertSame(['claim_lost' => 1], app(StripeReconciler::class)->run());
        $this->assertSame('payment_waiting', $this->order->fresh()->stripe_reconcile_result);
        $this->assertSame(['B' => 'owned'], $this->order->fresh()->stripe_reconcile_cursor);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
    }

    public function test_eleven_discovery_pages_survive_new_reconciler_instances(): void
    {
        config(['stripe_reconciliation.page_size' => 1, 'stripe_reconciliation.read_budget' => 4]);
        $this->order->update(['stripe_session_id' => null]);
        $objects = [];
        for ($i = 0; $i < 10; $i++) {
            $objects[] = array_replace($this->session, ['id' => 'cs_noise_'.$i, 'metadata' => []]);
        }
        $objects[] = $this->session;
        $error = null;
        $this->sessions($objects, $error);
        for ($run = 0; $run < 20; $run++) {
            $result = $this->runAgain();
            if ($result === 'paid') {
                break;
            }
            $this->assertSame('scan_incomplete', $result);
            $this->untouched();
        }
        $this->assertGreaterThan(0, $run);
        $this->assertLessThan(20, $run);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::count());
    }

    public function test_cursor_cannot_shrink_the_frozen_discovery_window(): void
    {
        config(['stripe_reconciliation.page_size' => 1, 'stripe_reconciliation.read_budget' => 4]);
        $this->order->update(['stripe_session_id' => null]);
        $objects = [];
        for ($i = 0; $i < 8; $i++) {
            $objects[] = array_replace($this->session, ['id' => 'cs_noise_'.$i, 'metadata' => []]);
        }
        $objects[] = $this->session;
        $error = null;
        $this->sessions($objects, $error);
        $this->assertSame('scan_incomplete', $this->runAgain());
        $cursor = $this->order->fresh()->stripe_reconcile_cursor;
        $cursor['from']++;
        $this->order->update(['stripe_reconcile_cursor' => $cursor]);
        $this->api->calls = [];
        $this->assertSame('manual_review', $this->runAgain());
        $this->assertSame([], $this->api->calls);
        $this->untouched();
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
    }

    public function test_refund_object_key_reordering_preserves_progress_and_fresh_validation(): void
    {
        config(['stripe_reconciliation.read_budget' => 5, 'stripe_reconciliation.page_size' => 1]);
        $this->charge['amount_refunded'] = 20000;
        $this->remote();
        $refunds = [];
        foreach (['re_z' => 7000, 're_a' => 7000, 're_m' => 6000] as $id => $amount) {
            $refunds[] = $refund = array_replace($this->refund(), ['id' => $id, 'amount' => $amount]);
            $this->api->responses['/v1/refunds/'.$id] = array_reverse($refund, true);
        }
        $this->api->responses['/v1/refunds'] = function ($params) use ($refunds) {
            $start = isset($params['starting_after']) ? array_search($params['starting_after'], array_column($refunds, 'id'), true) + 1 : 0;

            return $this->page(array_slice($refunds, $start, $params['limit']), $start + $params['limit'] < count($refunds));
        };
        $this->assertSame('scan_incomplete', $this->runAgain());
        $cursor = $this->order->fresh()->stripe_reconcile_cursor;
        $this->assertSame(['re_z', 're_a'], $cursor['refund_ids']);
        ksort($cursor['refunds']);
        $this->order->update(['stripe_reconcile_cursor' => $cursor]);
        $this->assertSame('refund_completed', $this->runAgain());
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $before = TransactionalEmail::count();
        $this->runAgain();
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame($before, TransactionalEmail::count());
    }

    #[DataProvider('r5Budgets')]
    public function test_r5_discovery_budget_has_bounded_outcome(int $budget, string $expected, int $enumeration = 4): void
    {
        config(['stripe_reconciliation.page_size' => 1, 'stripe_reconciliation.read_budget' => $enumeration,
            'stripe_reconciliation.validation_read_budget' => $budget]);
        $this->order->update(['stripe_session_id' => null]);
        $objects = [];
        for ($i = 0; $i < 7; $i++) {
            $objects[] = array_replace($this->session, ['id' => 'cs_noise_'.$i, 'metadata' => []]);
        }
        $objects[] = $this->session;
        $error = null;
        $this->sessions($objects, $error, true);
        for ($runs = 1; $runs <= 12; $runs++) {
            $result = $this->runAgain();
            if ($result !== 'scan_incomplete') {
                break;
            }
            $this->untouched();
        }
        $counts = $this->r5Calls();
        $this->assertSame($expected, $result, json_encode(['runs' => min($runs, 12), 'calls' => $counts]));
        $this->assertSame((int) ceil(16 / max(4, $enumeration)), $runs);
        $this->assertSame(['list' => $expected === 'paid' ? 18 : 17, 'session' => 1, 'pi' => 1, 'charge' => 1, 'refund' => 0], $counts);
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $this->assertNull($this->order->fresh()->stripe_reconcile_claim);
        $this->assertSame(0, $this->order->fresh()->stripe_reconcile_failures);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::where('kind', 'cancelled')->count());
        if ($expected === 'manual_review') {
            $this->untouched();
        } else {
            $this->assertSame('paid', $this->order->fresh()->payment_status);
            $this->assertSame('cs_r4', $this->order->fresh()->stripe_session_id);
            $this->assertSame(1, TransactionalEmail::where('kind', 'paid')->count());
        }
    }

    public static function r5Budgets(): array
    {
        // At this boundary enumeration leaves zero reads: list uniqueness + Session/PI/Charge + empty refund list = 5.
        return [[1, 'manual_review'], [4, 'manual_review'], [5, 'paid'], [6, 'paid'], [1014, 'paid'],
            [4, 'paid', 5], [4, 'paid', 6]];
    }

    private function r5Calls(): array
    {
        $counts = ['list' => 0, 'session' => 0, 'pi' => 0, 'charge' => 0, 'refund' => 0];
        foreach ($this->api->calls as $call) {
            $path = $call['path'];
            $kind = match (true) {
                in_array($path, ['/v1/checkout/sessions', '/v1/refunds', '/v1/events'], true) => 'list',
                str_starts_with($path, '/v1/checkout/sessions/') => 'session',
                str_starts_with($path, '/v1/payment_intents/') => 'pi',
                str_starts_with($path, '/v1/charges/') => 'charge',
                default => 'refund',
            };
            $counts[$kind]++;
        }

        return $counts;
    }

    public function test_r5_ten_discovery_pages_converge_with_default_validation_budget(): void
    {
        config(['stripe_reconciliation.page_size' => 1, 'stripe_reconciliation.read_budget' => 4]);
        $this->order->update(['stripe_session_id' => null]);
        $objects = [];
        for ($i = 0; $i < 9; $i++) {
            $objects[] = array_replace($this->session, ['id' => 'cs_noise_'.$i, 'metadata' => []]);
        }
        $objects[] = $this->session;
        $error = null;
        $this->sessions($objects, $error, true);
        for ($run = 1; $run <= 5; $run++) {
            $this->assertSame($run === 5 ? 'paid' : 'scan_incomplete', $this->runAgain());
            if ($run < 5) {
                $this->untouched();
            }
        }
        $this->assertSame(['list' => 22, 'session' => 1, 'pi' => 1, 'charge' => 1, 'refund' => 0], $this->r5Calls());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('kind', 'paid')->count());
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $this->assertSame('paid', $this->runAgain());
        $this->assertSame(1, TransactionalEmail::count());
    }

    public function test_r5_eight_refund_pages_converge_with_default_validation_budget(): void
    {
        config(['stripe_reconciliation.page_size' => 1, 'stripe_reconciliation.read_budget' => 4]);
        $this->charge['amount_refunded'] = 20000;
        $this->remote();
        $refunds = [];
        for ($i = 0; $i < 8; $i++) {
            $refunds[] = $refund = array_replace($this->refund(), ['id' => 're_part_'.$i, 'amount' => 2500]);
            $this->api->responses['/v1/refunds/'.$refund['id']] = $refund;
        }
        $this->api->responses['/v1/refunds'] = function ($params) use ($refunds) {
            $start = isset($params['starting_after']) ? array_search($params['starting_after'], array_column($refunds, 'id'), true) + 1 : 0;

            return $this->page(array_slice($refunds, $start, $params['limit']), $start + $params['limit'] < count($refunds));
        };
        for ($run = 1; $run <= 8; $run++) {
            $this->assertSame($run === 8 ? 'refund_completed' : 'scan_incomplete', $this->runAgain());
            if ($run < 8) {
                $this->assertSame('pending', $this->order->fresh()->payment_status);
                $this->assertSame(8, $this->product->fresh()->stock_quantity);
                $this->assertSame(0, TransactionalEmail::count());
            }
        }
        $this->assertSame(['list' => 9, 'session' => 8, 'pi' => 8, 'charge' => 8, 'refund' => 8], $this->r5Calls());
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $receipts = TransactionalEmail::count();
        $this->assertSame(2, $receipts);
        $this->assertSame(['order:'.$this->order->id.':cancelled', 'order:'.$this->order->id.':refund:completed'], TransactionalEmail::orderBy('event_key')->pluck('event_key')->all());
        $this->assertSame(0, TransactionalEmail::where('kind', 'paid')->count());
        for ($run = 1; $run <= 8; $run++) {
            $this->runAgain();
        }
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame($receipts, TransactionalEmail::count());
    }

    #[DataProvider('r5RefundOrdering')]
    public function test_r5_conflicting_refund_ids_are_manual_without_partial_effects(bool $reverse): void
    {
        $return = $this->returnRequest();
        $before = $return->fresh()->getAttributes();
        $this->charge['amount_refunded'] = 10000;
        $refunds = [$this->refund($return), array_replace($this->refund($return, 'failed'), ['id' => 're_other'])];
        if ($reverse) {
            $refunds = array_reverse($refunds);
        }
        $this->remote($refunds);
        $validator = app(StripeRecoveryValidator::class);
        $this->assertTrue($validator->session($this->order->fresh(), $this->attempt->fresh(), StripeObject::constructFrom($this->session), StripeObject::constructFrom($this->pi)));
        $this->assertTrue($validator->charge($this->order->fresh(), StripeObject::constructFrom($this->pi), StripeObject::constructFrom($this->charge), array_map(fn ($r) => StripeObject::constructFrom($r), $refunds)));
        $businessBefore = $this->r5Business($return);
        for ($run = 0; $run < 3; $run++) {
            $this->assertSame('manual_review', $this->runAgain());
            $this->assertSame($businessBefore, $this->r5Business($return));
            $this->assertSame($before, $return->fresh()->getAttributes());
            $this->assertSame(8, $this->product->fresh()->stock_quantity);
            $this->assertSame('pending', $this->order->fresh()->payment_status);
            $this->assertNull($this->order->fresh()->stripe_payment_intent);
            $this->assertSame(0, TransactionalEmail::count());
            $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
            $this->assertNull($this->order->fresh()->stripe_reconcile_claim);
            $this->assertSame(0, $this->order->fresh()->stripe_reconcile_failures);
        }
        $this->assertCount(12, $this->api->calls);
    }

    public static function r5RefundOrdering(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('r5TransactionBoundaries')]
    public function test_r5_business_boundaries_preserve_atomicity_and_retry(string $effect, string $stage): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->order->update(['stripe_session_id' => null]);
        $return = $effect === 'return' ? $this->returnRequest() : null;
        $refunds = [];
        if ($effect === 'expired') {
            $this->session = array_replace($this->session, ['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
        } elseif ($effect === 'async') {
            $this->failed();
        } elseif (in_array($effect, ['refund', 'return'], true)) {
            $this->charge['amount_refunded'] = $return ? 10000 : 20000;
            $refunds = [$this->refund($return)];
        }
        $this->remote($refunds);
        $this->api->responses['/v1/events'] = $this->page([$this->event()]);
        $before = $this->r5Business($return);
        $fired = false;
        if (in_array($stage, ['binding', 'status', 'stock', 'outbox'], true)) {
            DB::listen(function ($query) use ($stage, &$fired) {
                if ($fired || DB::transactionLevel() === 0) {
                    return;
                }
                $matches = match ($stage) {
                    'binding' => str_starts_with($query->sql, 'update "orders" set "stripe_session_id"'),
                    'status' => str_starts_with($query->sql, 'update ') && (str_contains($query->sql, '"payment_status" =') || str_contains($query->sql, '"refund_status" =')),
                    'stock' => str_starts_with($query->sql, 'update "products"') && str_contains($query->sql, '"stock_quantity"'),
                    'outbox' => str_starts_with($query->sql, 'insert into "transactional_emails"'),
                };
                if ($matches) {
                    $fired = true;
                    throw new \RuntimeException('R5 injected transaction failure');
                }
            });
            $this->assertSame('api_or_local_error', $this->runAgain());
        } else {
            $hook = function () use ($stage, &$fired) {
                $fired = true;
                if ($stage === 'completion') {
                    $this->travel(241)->seconds();
                } else {
                    throw new \RuntimeException('R5 injected commit boundary');
                }
            };
            $applier = new class($hook, $stage === 'before_commit') extends StripeStateApplier
            {
                public function __construct(private \Closure $hook, private bool $outerTransaction) {}

                public function recovered(int $orderId, object $session, ?object $pi, ?object $charge, array $refunds, string $claimId, string $expectedContext, ?object $asyncFailure = null): string
                {
                    $apply = function () use ($orderId, $session, $pi, $charge, $refunds, $claimId, $expectedContext, $asyncFailure) {
                        $result = parent::recovered($orderId, $session, $pi, $charge, $refunds, $claimId, $expectedContext, $asyncFailure);
                        ($this->hook)();

                        return $result;
                    };

                    // A surrounding transaction places the hook after all business writes,
                    // before their durable commit. Remote reads have already completed.
                    return $this->outerTransaction ? DB::transaction($apply) : $apply();
                }
            };
            $this->assertSame([$stage === 'completion' ? 'completion_not_owned' : 'api_or_local_error' => 1], (new StripeReconciler(app(StripeService::class), $applier))->run());
        }
        $this->assertTrue($fired, 'The requested boundary must actually be reached.');
        $afterCommit = in_array($stage, ['after_commit', 'completion'], true);
        if (! $afterCommit) {
            $this->assertSame($before, $this->r5Business($return));
        }
        $committed = $this->r5Business($return);
        $result = $this->runAgain();
        $this->assertContains($result, [$this->r5Outcome($effect), 'terminal_local_state']);
        $this->assertSame(['paid' => 'paid', 'expired' => 'failed', 'async' => 'failed', 'refund' => 'refunded', 'return' => 'paid'][$effect], $this->order->fresh()->payment_status);
        $this->assertSame($effect === 'paid' ? 8 : ($effect === 'return' ? 9 : 10), $this->product->fresh()->stock_quantity);
        $this->assertNull($this->order->fresh()->stripe_reconcile_cursor);
        $this->assertNull($this->order->fresh()->stripe_reconcile_claim);
        if ($afterCommit) {
            $this->assertSame($committed, $this->r5Business($return));
        }
        $complete = $this->r5Business($return);
        $this->runAgain();
        $this->assertSame($complete, $this->r5Business($return));
    }

    private function r5Outcome(string $effect): string
    {
        return ['paid' => 'paid', 'expired' => 'expired_or_canceled', 'async' => 'async_failed',
            'refund' => 'refund_completed', 'return' => 'return_refund_reconciled'][$effect];
    }

    private function r5Business(?ReturnRequest $return): array
    {
        return [Arr::only($this->order->fresh()->getAttributes(), ['status', 'payment_status', 'stripe_session_id', 'stripe_payment_intent',
            'stripe_refund_id', 'refund_status', 'stock_restored_at', 'refunded_at', 'late_stripe_payment_at']),
            $return?->fresh()->getAttributes(), $this->product->fresh()->stock_quantity,
            TransactionalEmail::orderBy('event_key')->pluck('event_key')->all()];
    }

    public static function r5TransactionBoundaries(): array
    {
        $cases = [];
        foreach (['paid', 'expired', 'async', 'refund', 'return'] as $effect) {
            foreach (['binding', 'status', 'stock', 'outbox', 'before_commit', 'after_commit', 'completion'] as $stage) {
                if ($effect !== 'paid' || $stage !== 'stock') {
                    $cases[$effect.'_'.$stage] = [$effect, $stage];
                }
            }
        }

        return $cases;
    }
}

class RemediationFourClient implements ClientInterface
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
