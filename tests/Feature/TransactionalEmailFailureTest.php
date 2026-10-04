<?php

namespace Tests\Feature;

use App\Mail\OrderCancelledMail;
use App\Mail\OrderPaidMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderShippedMail;
use App\Mail\RefundStatusMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ShippingRate;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutAttempts;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\StripeService;
use App\Services\TransactionalEmails;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\Concerns\UsesCommittedDatabase;
use Tests\TestCase;

class TransactionalEmailFailureTest extends TestCase
{
    // Delivery must execute after a real commit, not inside RefreshDatabase's transaction.
    use UsesCommittedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'logging.default' => 'null',
            'services.stripe.webhook_secret' => 'whsec_f1_test',
            'services.stripe.secret' => 'sk_test_f1_fake',
        ]);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_cod_order_remains_valid_and_reserved_when_smtp_fails_and_then_recovers(): void
    {
        $user = User::factory()->create();
        $product = $this->product(10);
        ShippingRate::create(['name' => 'Test', 'price' => 0, 'is_active' => true, 'sort_order' => 1]);
        $this->failMail();

        $this->actingAs($user)->withSession(['cart' => [
            $product->id => ['id' => $product->id, 'quantity' => 2],
        ]])->get(route('checkout.index'));
        $this->post(route('checkout.store'), [
            'checkout_token' => app(CheckoutAttempts::class)->issue(app(CartService::class))->token,
            'customer_type' => 'individual', 'first_name' => 'Test', 'last_name' => 'Client',
            'email' => $user->email, 'phone' => '0700000000', 'county' => 'Prahova',
            'city' => 'Ploiesti', 'address' => 'Test street', 'payment_method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->assertOrder($order, 'pending', 'pending', 8);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertSame(1, Order::count());
        $this->assertSame(2, $order->items()->firstOrFail()->quantity);
        $this->assertSame(200.0, (float) $order->total);

        $this->recoverMail();
        Mail::assertSent(OrderPlacedMail::class, 1);
        $this->assertOrder($order, 'pending', 'pending', 8);
    }

    public function test_paid_webhook_survives_smtp_failure_and_replay_sends_one_recovered_email(): void
    {
        $order = $this->order();
        $this->failMail();
        $payload = $this->paidPayload($order);
        $this->webhook('checkout.session.completed', $payload)->assertOk();
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->assertOrder($order, 'paid', 'processing', 6);

        $this->webhook('checkout.session.completed', $payload)->assertOk();
        $this->webhook('checkout.session.async_payment_succeeded', $payload)->assertOk();
        $this->recoverMail();
        $this->webhook('checkout.session.completed', $payload)->assertOk();
        $this->artisan('emails:deliver')->assertSuccessful();

        Mail::assertSent(OrderPaidMail::class, 1);
        $this->assertOrder($order, 'paid', 'processing', 6);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, TransactionalEmail::count());
        $this->assertSame($payload['payment_intent'], $order->fresh()->stripe_payment_intent);
    }

    public function test_full_refund_restores_stock_despite_smtp_failure_and_replay_is_idempotent(): void
    {
        $order = $this->order('paid', 'processing');
        $this->failMail();
        $payload = $this->refundPayload($order);
        $this->webhook('refund.updated', $payload)->assertOk();
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->assertOrder($order, 'refunded', 'cancelled', 10);
        $this->assertSame('completed', $order->fresh()->refund_status);
        $this->assertNotNull($order->fresh()->stock_restored_at);

        $this->webhook('refund.updated', $payload)->assertOk();
        $this->recoverMail();
        $this->webhook('refund.updated', $payload)->assertOk();
        $this->artisan('emails:deliver')->assertSuccessful();
        Mail::assertSent(RefundStatusMail::class, 1);
        Mail::assertSent(OrderCancelledMail::class, 1);
        $this->assertOrder($order, 'refunded', 'cancelled', 10);
    }

    public function test_already_completed_order_refund_repairs_legacy_missing_status_and_stock_once(): void
    {
        $order = $this->order('paid', 'processing');
        // Simulate the old failure between refund_status persistence and recovery.
        DB::table('orders')->where('id', $order->id)->update([
            'refund_status' => 'completed', 'stripe_refund_id' => 're_f1',
        ]);
        $this->failMail();
        $this->webhook('refund.updated', $this->refundPayload($order))->assertOk();
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->webhook('refund.updated', $this->refundPayload($order))->assertOk();
        $this->assertOrder($order, 'refunded', 'cancelled', 10);
        $this->assertNotNull($order->fresh()->stock_restored_at);
        $this->recoverMail();
        Mail::assertSent(RefundStatusMail::class, 1);
        Mail::assertSent(OrderCancelledMail::class, 1);
    }

    public function test_return_refund_restores_only_returned_quantity_and_recovers_notification_once(): void
    {
        $order = $this->order('paid', 'delivered');
        $return = $this->returnRequest($order);
        $this->failMail();
        $payload = $this->refundPayload($order, $return);
        $this->webhook('refund.updated', $payload)->assertOk();
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->webhook('refund.updated', $payload)->assertOk();
        $this->assertSame('completed', $return->fresh()->refund_status);
        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertNotNull($return->fresh()->stock_restored_at);
        $this->assertOrder($order, 'paid', 'delivered', 8);
        $this->recoverMail();
        Mail::assertSent(RefundStatusMail::class, fn ($mail) => $mail->state === 'completed' && $mail->amount === '200.00');
        Mail::assertSent(RefundStatusMail::class, 1);
        $this->assertOrder($order, 'paid', 'delivered', 8);
    }

    public function test_completed_return_replay_repairs_legacy_missing_stock_without_double_restoration(): void
    {
        $order = $this->order('paid', 'delivered');
        $return = $this->returnRequest($order);
        DB::table('returns')->where('id', $return->id)->update([
            'refund_status' => 'completed', 'status' => 'refunded', 'stripe_refund_id' => 're_f1',
        ]);
        $this->failMail();
        $this->webhook('refund.updated', $this->refundPayload($order, $return))->assertOk();
        $this->webhook('refund.updated', $this->refundPayload($order, $return))->assertOk();
        $this->assertOrder($order, 'paid', 'delivered', 8);
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->recoverMail();
        Mail::assertSent(RefundStatusMail::class, 1);
    }

    public function test_mark_as_failed_completes_recovery_with_smtp_unavailable(): void
    {
        $order = $this->order();
        $this->failMail();
        app(OrderService::class)->markAsFailed($order);
        $this->artisan('emails:deliver')->assertSuccessful();
        app(OrderService::class)->markAsFailed($order);
        $this->assertOrder($order, 'failed', 'cancelled', 10);
        $this->assertNotNull($order->fresh()->stock_restored_at);
        $this->recoverMail();
        Mail::assertSent(OrderCancelledMail::class, 1);
        $this->assertOrder($order, 'failed', 'cancelled', 10);
    }

    public function test_admin_order_and_return_refunds_finish_before_any_smtp_attempt(): void
    {
        $order = $this->order('paid', 'processing');
        $returnOrder = $this->order('paid', 'delivered');
        $return = $this->returnRequest($returnOrder);
        $this->fakeSuccessfulStripeRefunds();
        $this->failMail();
        app(StripeService::class)->refundPayment($order);
        app(StripeService::class)->refundReturn($return);
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->assertOrder($order, 'refunded', 'cancelled', 10);
        $this->assertOrder($returnOrder, 'paid', 'delivered', 8);
        $this->assertSame('completed', $return->fresh()->refund_status);
        $this->assertNotNull($return->fresh()->stock_restored_at);
        $this->recoverMail();
        Mail::assertSent(RefundStatusMail::class, 2);
        Mail::assertSent(OrderCancelledMail::class, 1);
    }

    public function test_business_rollback_discards_state_and_notification_and_cannot_send_before_commit(): void
    {
        $order = $this->order('paid', 'processing');
        Mail::fake();
        try {
            DB::transaction(function () use ($order): void {
                $order->update(['status' => 'shipped']);
                $email = TransactionalEmail::firstOrFail();
                app(TransactionalEmails::class)->deliver($email->id);
            });
            $this->fail('Delivery inside a business transaction should be rejected.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('outside business transactions', $exception->getMessage());
        }
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->assertOrder($order, 'paid', 'processing', 6);
        $this->assertSame(0, TransactionalEmail::count());
        Mail::assertNothingSent();

        DB::transaction(fn () => $order->fresh()->update(['status' => 'shipped']));
        Mail::assertNothingSent();
        $this->artisan('emails:deliver')->assertSuccessful();
        Mail::assertSent(OrderShippedMail::class, 1);
        $this->assertOrder($order, 'paid', 'shipped', 6);
    }

    public function test_backoff_exhaustion_is_visible_and_manual_retry_does_not_resend_sent_mail(): void
    {
        $order = $this->order();
        $this->failMail();
        app(OrderService::class)->markAsPaid($order);
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->assertSame(1, TransactionalEmail::firstOrFail()->attempts);
        foreach ([61, 301, 901, 3601] as $index => $seconds) {
            $this->travel($seconds)->seconds();
            $this->artisan('emails:deliver')->assertExitCode($index === 3 ? 1 : 0);
        }
        $email = TransactionalEmail::firstOrFail();
        $this->assertSame(5, $email->attempts);
        $this->assertNotNull($email->failed_at);
        $this->assertSame(\RuntimeException::class, $email->last_error_type);
        $this->artisan('emails:deliver', ['--status' => true])
            ->expectsOutput('Pending: 0; failed: 1; overdue by 10 minutes: 0')->assertFailed();
        Mail::fake();
        $this->artisan('emails:deliver', ['--retry' => $email->id])->assertSuccessful();
        $this->artisan('emails:deliver', ['--retry' => $email->id])->assertFailed();
        $this->artisan('emails:deliver')->assertSuccessful();
        Mail::assertSent(OrderPaidMail::class, 1);
        $this->assertOrder($order, 'paid', 'processing', 6);
    }

    public function test_missing_scheduler_is_visible_without_sending_or_changing_business_state(): void
    {
        $order = $this->order();
        Mail::fake();
        app(OrderService::class)->markAsPaid($order);
        $this->travel(11)->minutes();
        $this->artisan('emails:deliver', ['--status' => true])
            ->expectsOutput('Pending: 1; failed: 0; overdue by 10 minutes: 1')->assertFailed();
        Mail::assertNothingSent();
        $this->assertOrder($order, 'paid', 'processing', 6);
    }

    public function test_failure_log_does_not_contain_exception_message_or_recipient(): void
    {
        $order = $this->order();
        $this->failMail();
        Log::shouldReceive('warning')->once()->with('Transactional email delivery failed.', Mockery::on(
            fn (array $context) => array_keys($context) === ['delivery_id', 'kind', 'attempt', 'error_type']
                && $context['error_type'] === \RuntimeException::class
                && ! str_contains(json_encode($context), 'SECRET-SMTP'),
        ));
        app(OrderService::class)->markAsPaid($order);
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->assertOrder($order, 'paid', 'processing', 6);
    }

    public function test_attachment_failure_is_recoverable_and_real_mailable_renders_after_retry(): void
    {
        $order = $this->order();
        $invoice = Mockery::mock(InvoiceService::class);
        $invoice->shouldReceive('generate')->once()->andThrow(new \RuntimeException('PDF unavailable'));
        $this->app->instance(InvoiceService::class, $invoice);
        app(OrderService::class)->markAsPaid($order);
        $this->artisan('emails:deliver')->assertSuccessful();
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertCount(0, $transport->messages());
        $this->assertOrder($order, 'paid', 'processing', 6);

        $invoice = Mockery::mock(InvoiceService::class);
        $invoice->shouldReceive('generate')->once()->andReturn(new class
        {
            public function output(): string
            {
                return '%PDF-1.4 test attachment';
            }
        });
        $this->app->instance(InvoiceService::class, $invoice);
        $this->travel(61)->seconds();
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->assertCount(1, $transport->messages());
        $this->assertNotNull(TransactionalEmail::firstOrFail()->sent_at);
        $this->assertOrder($order, 'paid', 'processing', 6);
    }

    public function test_invalid_recovery_options_cannot_reset_a_failed_receipt_or_send_mail(): void
    {
        $order = $this->order();
        Mail::fake();
        app(OrderService::class)->markAsPaid($order);
        $email = TransactionalEmail::firstOrFail();
        $email->update(['failed_at' => now(), 'attempts' => 5]);

        $this->artisan('emails:deliver', ['--retry' => $email->id, '--limit' => 0])->assertFailed();
        $this->artisan('emails:deliver', ['--retry' => $email->id, '--status' => true])->assertFailed();
        $this->artisan('emails:deliver', ['--retry' => 'invalid'])->assertFailed();
        $this->assertSame(5, $email->fresh()->attempts);
        $this->assertNotNull($email->fresh()->failed_at);
        Mail::assertNothingSent();
        $this->assertOrder($order, 'paid', 'processing', 6);
    }

    private function failMail(): void
    {
        $pending = Mockery::mock(PendingMail::class);
        $pending->shouldReceive('send')->andThrow(new \RuntimeException('SECRET-SMTP recipient@example.test'));
        Mail::shouldReceive('to')->andReturn($pending);
        Mail::shouldReceive('getDefaultDriver')->andReturn('array');
    }

    private function recoverMail(): void
    {
        Mail::fake();
        $this->travel(61)->seconds();
        $this->artisan('emails:deliver')->assertSuccessful();
        $this->artisan('emails:deliver')->assertSuccessful();
    }

    private function assertOrder(Order $order, string $payment, string $status, int $stock): void
    {
        $this->assertSame($payment, $order->fresh()->payment_status);
        $this->assertSame($status, $order->fresh()->status);
        $this->assertSame($stock, $order->items()->firstOrFail()->product->stock_quantity);
    }

    private function product(int $stock): Product
    {
        $category = Category::create(['name' => 'F1', 'slug' => 'f1-'.uniqid()]);

        return Product::create([
            'category_id' => $category->id, 'name' => 'F1 product', 'slug' => 'f1-'.uniqid(),
            'sku' => 'F1-'.uniqid(), 'purchase_price' => 50, 'selling_price' => 100,
            'stock_quantity' => $stock, 'weight' => 1,
        ]);
    }

    private function order(string $payment = 'pending', string $status = 'pending'): Order
    {
        $product = $this->product(6);
        $order = Order::create([
            'order_number' => 'NOV-F1-'.uniqid(), 'first_name' => 'Test', 'last_name' => 'User',
            'email' => 'f1@example.test', 'phone' => '0700000000', 'county' => 'Prahova',
            'city' => 'Ploiesti', 'address' => 'Test street', 'subtotal' => 400, 'shipping_cost' => 0,
            'total' => 400, 'payment_method' => 'stripe', 'payment_status' => $payment, 'status' => $status,
            'stripe_session_id' => 'cs_f1_'.uniqid(),
            'stripe_payment_intent' => $payment === 'paid' ? 'pi_f1_'.uniqid() : null,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
            'price' => 100, 'quantity' => 4, 'total' => 400,
        ]);

        return $order;
    }

    private function returnRequest(Order $order): ReturnRequest
    {
        $return = ReturnRequest::create([
            'order_id' => $order->id, 'user_id' => User::factory()->create()->id,
            'reason' => 'F1 test', 'status' => 'received', 'requested_at' => now(), 'refund_amount' => 200,
        ]);
        $return->items()->create([
            'order_item_id' => $order->items()->firstOrFail()->id, 'quantity' => 2,
            'unit_price' => 100, 'line_refund_amount' => 200,
        ]);

        return $return;
    }

    private function paidPayload(Order $order): array
    {
        return [
            'id' => $order->stripe_session_id, 'payment_intent' => 'pi_confirmed_'.$order->id,
            'payment_status' => 'paid', 'currency' => 'ron', 'amount_total' => 40000,
            'metadata' => ['order_id' => (string) $order->id],
        ];
    }

    private function refundPayload(Order $order, ?ReturnRequest $return = null): array
    {
        return [
            'id' => 're_f1', 'payment_intent' => $order->stripe_payment_intent,
            'status' => 'succeeded', 'currency' => 'ron', 'amount' => $return ? 20000 : 40000,
            'metadata' => array_filter([
                'order_id' => (string) $order->id, 'return_request_id' => $return ? (string) $return->id : null,
            ]),
        ];
    }

    private function webhook(string $type, array $object)
    {
        $payload = json_encode(['id' => 'evt_f1_'.uniqid(), 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_f1_test');

        return $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
        ], $payload);
    }

    private function fakeSuccessfulStripeRefunds(): void
    {
        ApiRequestor::setHttpClient(new class implements ClientInterface
        {
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
            {
                return [json_encode(['id' => 're_f1', 'object' => 'refund', 'status' => 'succeeded']), 200, []];
            }
        });
    }
}
