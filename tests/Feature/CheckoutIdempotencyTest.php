<?php

namespace Tests\Feature;

use App\Mail\OrderPaidMail;
use App\Mail\OrderPlacedMail;
use App\Models\Category;
use App\Models\CheckoutAttempt;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Symfony\Component\Process\Process;
use Tests\Concerns\UsesCommittedDatabase;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use UsesCommittedDatabase;

    private User $user;

    private Product $product;

    private CheckoutStripeClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.default' => 'null', 'services.stripe.secret' => 'sk_test_f2', 'services.stripe.webhook_secret' => 'whsec_f2']);
        Mail::fake();
        $this->user = User::factory()->create();
        $category = Category::create(['name' => 'F2', 'slug' => 'f2']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'F2 product', 'slug' => 'f2-product', 'sku' => 'F2',
            'purchase_price' => 50, 'selling_price' => 100, 'stock_quantity' => 10, 'weight' => 1,
        ]);
        ShippingRate::create(['name' => 'F2 shipping', 'price' => 0, 'is_active' => true, 'sort_order' => 1]);
        $this->stripe = new CheckoutStripeClient;
        ApiRequestor::setHttpClient($this->stripe);
        $this->actingAs($this->user)->withSession(['cart' => [
            $this->product->id => ['id' => $this->product->id, 'quantity' => 2],
        ]]);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_refresh_and_two_forms_share_one_server_issued_token(): void
    {
        $first = $this->token();
        $this->assertSame($first, $this->token());
        $this->assertSame(1, CheckoutAttempt::count());
        $this->assertSame(0, Order::count());
    }

    public function test_cod_double_submit_and_retry_after_success_create_one_order_stock_and_email(): void
    {
        $data = $this->data($this->token());
        $this->post(route('checkout.store'), $data)->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->post(route('checkout.store'), $data)->assertRedirect(route('checkout.success', $order));
        $this->get(route('checkout.success', $order))->assertRedirect(route('home'));
        $this->post(route('checkout.store'), $data)->assertRedirect(route('checkout.success', $order));
        $this->assertCommercialState(1);
        $this->assertSame(0, $this->stripe->requests);
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->artisan('emails:deliver')->assertSuccessful();
        Mail::assertSent(OrderPlacedMail::class, 1);
    }

    public function test_missing_unknown_and_other_users_tokens_cannot_create_orders(): void
    {
        $token = $this->token();
        $data = $this->data($token);
        unset($data['checkout_token']);
        $this->post(route('checkout.store'), $data)->assertSessionHasErrors('checkout_token');
        $this->post(route('checkout.store'), $this->data('e00ff873-aa52-4019-939a-3e4e15c5dd09'))->assertSessionHasErrors('checkout_token');
        $this->actingAs(User::factory()->create());
        $this->post(route('checkout.store'), $this->data($token))->assertSessionHasErrors('checkout_token');
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }

    public function test_reusing_consumed_token_with_changed_payment_or_address_is_rejected(): void
    {
        $data = $this->data($this->token());
        $this->post(route('checkout.store'), $data)->assertSessionHasNoErrors();
        foreach ([['payment_method' => 'stripe'], ['address' => 'Changed address']] as $change) {
            $this->post(route('checkout.store'), array_merge($data, $change))->assertSessionHasErrors('checkout_token');
        }
        $this->assertCommercialState(1);
        $this->assertSame(0, $this->stripe->requests);
    }

    public function test_cart_mutation_invalidates_unused_form_and_allows_a_new_purchase_after_clear(): void
    {
        $old = $this->token();
        $this->post(route('cart.update', $this->product), ['quantity' => 3]);
        $this->post(route('checkout.store'), $this->data($old))->assertSessionHasErrors('checkout_token');
        $new = $this->token();
        $this->assertNotSame($old, $new);
        $this->post(route('checkout.store'), $this->data($new))->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->get(route('checkout.success', $order));
        $this->post(route('cart.add', $this->product), ['quantity' => 2]);
        $again = $this->token();
        $this->assertNotSame($new, $again);
        $this->post(route('checkout.store'), $this->data($again))->assertSessionHasNoErrors();
        $this->assertSame(2, Order::count());
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
        $this->assertSame(2, TransactionalEmail::count());
    }

    public function test_expired_unused_form_is_rejected_but_completed_replay_is_not_expired(): void
    {
        $old = $this->token();
        $this->travel(121)->minutes();
        $this->post(route('checkout.store'), $this->data($old))->assertSessionHasErrors('checkout_token');
        $new = $this->token();
        $this->post(route('checkout.store'), $this->data($new))->assertSessionHasNoErrors();
        $this->travel(3)->days();
        $this->post(route('checkout.store'), $this->data($new))->assertRedirect(route('checkout.success', Order::firstOrFail()));
        $this->assertCommercialState(1);
    }

    public function test_order_receipt_and_token_consumption_roll_back_together_then_same_token_can_retry(): void
    {
        $data = $this->data($this->token());
        DB::statement("CREATE TEMP TRIGGER f2_fail_consume BEFORE UPDATE OF order_id ON checkout_attempts
            WHEN NEW.order_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'F2 consume failure'); END");
        try {
            $this->post(route('checkout.store'), $data)->assertSessionHas('error');
        } finally {
            DB::statement('DROP TRIGGER f2_fail_consume');
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertNull(CheckoutAttempt::firstOrFail()->order_id);
        $this->assertNull(CheckoutAttempt::firstOrFail()->request_hash);
        $this->post(route('checkout.store'), $data)->assertSessionHasNoErrors();
        $this->assertCommercialState(1);
    }

    public function test_database_uniqueness_rejects_duplicate_tokens(): void
    {
        $this->token();
        $attempt = CheckoutAttempt::firstOrFail();
        $attributes = $attempt->getAttributes();
        unset($attributes['id']);
        try {
            CheckoutAttempt::create($attributes);
            $this->fail('The unique token must be enforced by the database.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('unique', strtolower($exception->getMessage()));
        }
        $this->assertSame(1, CheckoutAttempt::count());
    }

    public function test_stripe_double_submit_returns_same_session_without_duplicate_api_call(): void
    {
        $data = $this->data($this->token(), 'stripe');
        $this->post(route('checkout.store'), $data)->assertRedirect('https://checkout.stripe.com/f2/cs_f2_1');
        $this->post(route('checkout.store'), $data)->assertRedirect('https://checkout.stripe.com/f2/cs_f2_1');
        $this->assertCommercialState(0);
        $this->assertSame(1, $this->stripe->requests);
        $this->assertCount(1, $this->stripe->sessions);
        $this->assertSame('novelion-checkout-'.$data['checkout_token'], array_key_first($this->stripe->sessions));
        $this->paidWebhook()->assertOk();
        $this->paidWebhook()->assertOk();
        $this->post(route('checkout.store'), $data)->assertRedirect(route('checkout.success', Order::firstOrFail()));
        $this->assertCommercialState(1);
        $this->assertSame('paid', Order::firstOrFail()->payment_status);
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->artisan('emails:deliver')->assertSuccessful();
        Mail::assertSent(OrderPaidMail::class, 1);
    }

    public function test_stripe_lost_reply_retries_same_external_session_and_frozen_parameters(): void
    {
        $data = $this->data($this->token(), 'stripe');
        $this->stripe->loseNextReply = true;
        $this->post(route('checkout.store'), $data)->assertSessionHas('error');
        $order = Order::firstOrFail();
        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->stock_restored_at);
        $this->assertNull($order->stripe_session_id);
        $this->assertCommercialState(0);
        $parameters = CheckoutAttempt::firstOrFail()->stripe_parameters;
        $order->items()->firstOrFail()->update(['product_name' => 'Renamed after timeout']);
        config(['app.url' => 'https://changed.invalid']);
        $this->post(route('checkout.store'), $data)->assertRedirect('https://checkout.stripe.com/f2/cs_f2_1');
        $this->assertSame($parameters, CheckoutAttempt::firstOrFail()->stripe_parameters);
        $this->assertSame(2, $this->stripe->requests);
        $this->assertCount(1, $this->stripe->sessions);
        $this->assertCommercialState(0);
    }

    public function test_stripe_local_save_failure_after_external_creation_recovers_with_same_key(): void
    {
        $data = $this->data($this->token(), 'stripe');
        $sessionIdWritten = false;
        DB::listen(function ($query) use (&$sessionIdWritten): void {
            $sessionIdWritten |= str_contains($query->sql, 'update "orders"') && str_contains($query->sql, 'stripe_session_id');
        });
        DB::statement("CREATE TEMP TRIGGER f2_fail_session BEFORE UPDATE OF stripe_session_url ON checkout_attempts
            WHEN NEW.stripe_session_url IS NOT NULL BEGIN SELECT RAISE(ABORT, 'F2 session save failure'); END");
        try {
            $this->post(route('checkout.store'), $data)->assertSessionHas('error');
        } finally {
            DB::statement('DROP TRIGGER f2_fail_session');
        }
        $this->assertTrue((bool) $sessionIdWritten);
        $this->assertNull(Order::firstOrFail()->stripe_session_id);
        $this->assertNull(CheckoutAttempt::firstOrFail()->stripe_session_url);
        $this->assertNotNull(CheckoutAttempt::firstOrFail()->stripe_parameters);
        $this->assertNotNull(CheckoutAttempt::firstOrFail()->stripe_started_at);
        $this->assertCommercialState(0);
        $this->post(route('checkout.store'), $data)->assertRedirect('https://checkout.stripe.com/f2/cs_f2_1');
        $this->assertCount(1, $this->stripe->sessions);
        $this->assertSame(2, $this->stripe->requests);
        $this->assertCommercialState(0);
    }

    public function test_stripe_retry_after_key_safety_window_does_not_make_a_new_api_request(): void
    {
        $data = $this->data($this->token(), 'stripe');
        $this->stripe->loseNextReply = true;
        $this->post(route('checkout.store'), $data)->assertSessionHas('error');
        $this->travel(23)->hours();
        $this->post(route('checkout.store'), $data)->assertSessionHas('error');
        $this->assertSame(1, $this->stripe->requests);
        $this->assertCount(1, $this->stripe->sessions);
        $this->assertCommercialState(0);
    }

    public function test_cancelled_stripe_token_resolves_to_old_order_and_fresh_form_can_start_a_new_attempt(): void
    {
        $data = $this->data($this->token(), 'stripe');
        $this->post(route('checkout.store'), $data)->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->post(route('checkout.cancel-order', $order));
        $this->post(route('checkout.store'), $data)->assertRedirect(route('my-orders.show', $order));
        $this->assertSame(1, $this->stripe->requests);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $new = $this->token();
        $this->assertNotSame($data['checkout_token'], $new);
        $this->post(route('checkout.store'), $this->data($new, 'stripe'))->assertRedirect('https://checkout.stripe.com/f2/cs_f2_2');
        $this->assertSame(2, Order::count());
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
    }

    public function test_two_concurrent_cod_processes_resolve_to_one_order_and_receipt(): void
    {
        $this->assertConcurrentCheckout('cash');
    }

    public function test_two_concurrent_stripe_processes_resolve_to_one_order_and_external_session(): void
    {
        $this->assertConcurrentCheckout('stripe');
    }

    private function assertConcurrentCheckout(string $payment): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('Two-process checkout test requires proc_open.');
        }
        $data = $this->data($this->token(), $payment);
        $data = array_merge($data, [
            'shipping_first_name' => $data['first_name'], 'shipping_last_name' => $data['last_name'],
            'shipping_phone' => $data['phone'], 'shipping_county' => $data['county'],
            'shipping_city' => $data['city'], 'shipping_address' => $data['address'], 'shipping_postal_code' => null,
        ]);
        $directory = sys_get_temp_dir().'/novelion-f2-'.bin2hex(random_bytes(12));
        mkdir($directory);
        $workers = [];
        $pdo = null;
        try {
            $pdo = new \PDO('sqlite:'.$directory.'/fixture.sqlite');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode=WAL');
            // Copy only this test's in-memory schema and synthetic data.
            $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tables as $table) {
                $pdo->exec($table->sql);
                foreach (DB::table($table->name)->get() as $row) {
                    $values = (array) $row;
                    $columns = implode(',', array_map(fn ($column) => '"'.$column.'"', array_keys($values)));
                    $placeholders = implode(',', array_fill(0, count($values), '?'));
                    $pdo->prepare('INSERT INTO "'.$table->name.'" ('.$columns.') VALUES ('.$placeholders.')')->execute(array_values($values));
                }
            }
            foreach (DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL") as $index) {
                $pdo->exec($index->sql);
            }
            file_put_contents($directory.'/request.json', json_encode([
                'user_id' => $this->user->id, 'cart' => session('cart'), 'data' => $data,
            ], JSON_THROW_ON_ERROR));
            foreach ([1, 2] as $id) {
                $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/checkout-concurrency-worker.php'), base_path(), $directory, (string) $id], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/fixture.sqlite',
                    'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
                ], timeout: 30);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            while (! is_file($directory.'/ready-1') || ! is_file($directory.'/ready-2')) {
                if (microtime(true) >= $deadline) {
                    $this->fail('Both checkout workers must reach the concurrency barrier.');
                }
                usleep(10000);
                clearstatcache();
            }
            touch($directory.'/start');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $this->assertJson($worker->getOutput(), $worker->getOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertSame($results[0]['order_id'], $results[1]['order_id']);
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
            $this->assertSame(8, (int) $pdo->query('SELECT stock_quantity FROM products')->fetchColumn());
            $this->assertSame($payment === 'cash' ? 1 : 0, (int) $pdo->query('SELECT COUNT(*) FROM transactional_emails')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM checkout_attempts WHERE order_id IS NOT NULL')->fetchColumn());
            if ($payment === 'stripe') {
                $this->assertSame('cs_concurrent_f2', $results[0]['session_id']);
                $this->assertSame($results[0]['session_id'], $results[1]['session_id']);
                $this->assertCount(1, json_decode(file_get_contents($directory.'/stripe.json'), true, flags: JSON_THROW_ON_ERROR));
            }
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            $pdo = null;
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function test_expired_recovers_rolled_back_session_without_browser_retry_and_only_restores_once(): void
    {
        $session = $this->orphanedSession();
        $this->recoveryWebhook($session)->assertOk();
        $this->assertSame($session['id'], Order::firstOrFail()->stripe_session_id);
        $this->assertSame('failed', Order::firstOrFail()->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertNotNull(Order::firstOrFail()->stock_restored_at);
        $receipts = TransactionalEmail::count();
        $this->recoveryWebhook($session)->assertOk();
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame($receipts, TransactionalEmail::count());
        $other = $session;
        $other['id'] = 'cs_incompatible';
        $this->recoveryWebhook($other)->assertOk();
        $this->assertSame($session['id'], Order::firstOrFail()->stripe_session_id);
        $this->assertSame(1, $this->stripe->requests);
    }

    public function test_paid_webhook_recovers_orphan_and_receipt_once(): void
    {
        $session = $this->orphanedSession();
        $session['status'] = 'complete';
        $session['payment_status'] = 'paid';
        $session['payment_intent'] = 'pi_recovered';
        foreach (['checkout.session.completed', 'checkout.session.async_payment_succeeded'] as $type) {
            $this->recoveryWebhook($session, $type)->assertOk();
        }
        $this->assertSame('paid', Order::firstOrFail()->payment_status);
        $this->assertSame('pi_recovered', Order::firstOrFail()->stripe_payment_intent);
        $this->assertCommercialState(1);
    }

    public function test_recovery_refuses_inconsistent_identity_and_commercial_fields(): void
    {
        $valid = $this->orphanedSession();
        $variants = [
            ['amount_total' => 1], ['currency' => 'eur'], ['mode' => 'subscription'],
            ['client_reference_id' => 'another-attempt'], ['status' => 'open'],
            ['payment_status' => 'paid'],
        ];
        foreach (['order_id', 'user_id', 'checkout_attempt', 'request_hash', 'checkout_fingerprint'] as $field) {
            $metadata = $valid['metadata'];
            $metadata[$field] = '999';
            $variants[] = ['metadata' => $metadata];
        }
        foreach ($variants as $changes) {
            $this->recoveryWebhook(array_replace($valid, $changes))->assertOk();
            $this->assertNull(Order::firstOrFail()->stripe_session_id);
            $this->assertSame('pending', Order::firstOrFail()->payment_status);
            $this->assertSame(8, $this->product->fresh()->stock_quantity);
            $this->assertSame(0, TransactionalEmail::count());
        }
        $this->recoveryWebhook($valid)->assertOk();
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }

    public function test_recovered_binding_survives_commercial_rollback_and_webhook_retry(): void
    {
        $session = $this->orphanedSession();
        DB::unprepared("CREATE TEMP TRIGGER reject_a1_restore BEFORE UPDATE OF stock_quantity ON products WHEN NEW.stock_quantity > OLD.stock_quantity BEGIN SELECT RAISE(ABORT, 'A1 webhook rollback'); END");
        $this->recoveryWebhook($session)->assertStatus(500);
        $this->assertSame($session['id'], Order::firstOrFail()->stripe_session_id);
        $this->assertSame('pending', Order::firstOrFail()->payment_status);
        $this->assertNull(Order::firstOrFail()->stock_restored_at);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
        DB::unprepared('DROP TRIGGER reject_a1_restore');
        $this->recoveryWebhook($session)->assertOk();
        $this->recoveryWebhook($session)->assertOk();
        $this->assertSame('failed', Order::firstOrFail()->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());
    }

    public function test_recovery_cannot_attach_event_to_another_existing_order(): void
    {
        $session = $this->orphanedSession();
        $other = Order::firstOrFail()->replicate();
        $other->order_number = 'A1-OTHER';
        $other->save();
        $session['metadata']['order_id'] = (string) $other->id;
        $this->recoveryWebhook($session)->assertOk();
        $this->assertNull($other->fresh()->stripe_session_id);
        $this->assertNull(Order::query()->whereKeyNot($other->id)->firstOrFail()->stripe_session_id);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
    }

    public function test_recovery_accepts_json_object_key_reordering(): void
    {
        $session = $this->orphanedSession();
        $attempt = CheckoutAttempt::firstOrFail();
        $reorder = function (array $value) use (&$reorder): array {
            if (! array_is_list($value)) {
                krsort($value);
            }
            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $value[$key] = $reorder($item);
                }
            }

            return $value;
        };
        $attempt->update(['stripe_parameters' => $reorder($attempt->stripe_parameters)]);
        $this->recoveryWebhook($session)->assertOk();
        $this->assertSame('failed', Order::firstOrFail()->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }

    private function orphanedSession(): array
    {
        DB::unprepared("CREATE TEMP TRIGGER reject_a1_url BEFORE UPDATE OF stripe_session_url ON checkout_attempts WHEN NEW.stripe_session_url IS NOT NULL BEGIN SELECT RAISE(ABORT, 'A1 persistence failure'); END");
        $this->post(route('checkout.store'), $this->data($this->token(), 'stripe'))->assertSessionHas('error');
        DB::unprepared('DROP TRIGGER reject_a1_url');
        $attempt = CheckoutAttempt::firstOrFail();
        $this->assertNull(Order::firstOrFail()->stripe_session_id);
        $this->assertNull($attempt->stripe_session_url);
        $this->assertNotNull($attempt->stripe_started_at);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $external = array_values($this->stripe->sessions)[0];

        return array_merge($external['response'], [
            'mode' => 'payment', 'status' => 'expired', 'payment_status' => 'unpaid',
            'currency' => 'ron', 'amount_total' => 20000, 'payment_intent' => null,
            'metadata' => $attempt->stripe_parameters['metadata'], 'client_reference_id' => $attempt->token,
        ]);
    }

    private function recoveryWebhook(array $session, string $type = 'checkout.session.expired')
    {
        $payload = json_encode(['id' => 'evt_a1', 'object' => 'event', 'type' => $type, 'data' => ['object' => $session]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_f2');

        return $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}"], $payload);
    }

    private function token(): string
    {
        return $this->get(route('checkout.index'))->assertOk()->viewData('checkoutToken');
    }

    private function data(string $token, string $payment = 'cash'): array
    {
        return [
            'checkout_token' => $token, 'customer_type' => 'individual', 'first_name' => 'F2', 'last_name' => 'User',
            'email' => $this->user->email, 'phone' => '0700000000', 'county' => 'Prahova', 'city' => 'Ploiesti',
            'address' => 'F2 street', 'payment_method' => $payment,
        ];
    }

    private function assertCommercialState(int $receipts): void
    {
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Order::firstOrFail()->items()->count());
        $this->assertSame(2, Order::firstOrFail()->items()->firstOrFail()->quantity);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame($receipts, TransactionalEmail::count());
        $this->assertSame(1, CheckoutAttempt::where('order_id', Order::firstOrFail()->id)->count());
        Mail::assertNothingSent();
    }

    private function paidWebhook()
    {
        $order = Order::firstOrFail();
        $payload = json_encode(['id' => 'evt_f2', 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => $order->stripe_session_id, 'object' => 'checkout.session', 'payment_intent' => 'pi_f2',
            'payment_status' => 'paid', 'currency' => 'ron', 'amount_total' => 20000,
            'metadata' => ['order_id' => (string) $order->id],
        ]]]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_f2');

        return $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}"], $payload);
    }
}

class CheckoutStripeClient implements ClientInterface
{
    public int $requests = 0;

    public array $sessions = [];

    public bool $loseNextReply = false;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $this->requests++;
        $key = null;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Idempotency-Key: ')) {
                $key = substr($header, strlen('Idempotency-Key: '));
            }
        }
        if ($key === null) {
            throw new \LogicException('Checkout must send a Stripe idempotency key.');
        }
        if (isset($this->sessions[$key]) && $this->sessions[$key]['params'] !== $params) {
            throw new \LogicException('A Stripe key must retain identical request parameters.');
        }
        if (! isset($this->sessions[$key])) {
            $id = 'cs_f2_'.(count($this->sessions) + 1);
            $this->sessions[$key] = ['params' => $params, 'response' => [
                'id' => $id, 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/f2/'.$id,
            ]];
        }
        if ($this->loseNextReply) {
            $this->loseNextReply = false;
            throw ApiConnectionException::factory('Simulated lost reply AFTER Stripe session creation.');
        }

        return [json_encode($this->sessions[$key]['response']), 200, []];
    }
}
