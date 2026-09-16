<?php

namespace Tests\Feature;

use App\Mail\OrderCancelledMail;
use App\Mail\OrderPaidMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_novelion';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.default' => 'null',
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        Mail::fake();
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_a_late_payment_after_expiry_does_not_reactivate_a_cancelled_order_or_reserve_stock_again(): void
    {
        $order = $this->createStripeOrder();
        $order->update(['stripe_session_id' => 'cs_test_sequence']);

        $this->sendWebhook('checkout.session.expired', [
            'id' => 'cs_test_sequence',
            'metadata' => ['order_id' => (string) $order->id],
        ])->assertOk();

        $latePayload = [
            'id' => 'cs_test_sequence',
            'payment_intent' => 'pi_test_sequence',
            'payment_status' => 'paid',
            'currency' => 'ron',
            'amount_total' => 40000,
            'metadata' => ['order_id' => (string) $order->id],
        ];
        $this->sendWebhook('checkout.session.completed', $latePayload)->assertOk();
        $firstRecordedAt = $order->fresh()->late_stripe_payment_at;
        $this->sendWebhook('checkout.session.completed', $latePayload)->assertOk();

        $order->refresh();
        $product = $order->items()->firstOrFail()->product;
        $product->refresh();

        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->stock_restored_at);
        $this->assertSame(10, $product->stock_quantity);
        $this->assertNotNull($order->late_stripe_payment_at);
        $this->assertEquals($firstRecordedAt, $order->late_stripe_payment_at);
        $this->assertSame('pi_test_sequence', $order->stripe_payment_intent);
        Mail::assertNotSent(OrderPaidMail::class);
    }

    public function test_duplicate_completed_events_send_only_one_confirmation_email(): void
    {
        $order = $this->createStripeOrder();
        $order->update(['stripe_session_id' => 'cs_test_duplicate']);

        $payload = [
            'id' => 'cs_test_duplicate',
            'payment_intent' => 'pi_test_duplicate',
            'payment_status' => 'paid',
            'currency' => 'ron',
            'amount_total' => 40000,
            'metadata' => ['order_id' => (string) $order->id],
        ];

        $this->sendWebhook('checkout.session.completed', $payload)->assertOk();
        $this->sendWebhook('checkout.session.completed', $payload)->assertOk();

        Mail::assertSent(OrderPaidMail::class, 1);
    }

    public function test_async_payment_success_marks_the_order_as_paid(): void
    {
        $order = $this->createStripeOrder();
        $order->update(['stripe_session_id' => 'cs_test_async']);

        $this->sendWebhook('checkout.session.async_payment_succeeded', [
            'id' => 'cs_test_async',
            'payment_intent' => 'pi_test_async',
            'payment_status' => 'paid',
            'currency' => 'ron',
            'amount_total' => 40000,
            'metadata' => ['order_id' => (string) $order->id],
        ])->assertOk();

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
    }

    public function test_a_session_belonging_to_another_order_cannot_pay_this_order(): void
    {
        $order = $this->createStripeOrder();
        $other = $this->createStripeOrder();

        $this->sendWebhook('checkout.session.completed', $this->paidSession($order, [
            'id' => $other->stripe_session_id,
        ]))->assertOk();

        $this->assertSame('pending', $order->fresh()->payment_status);
        Mail::assertNotSent(OrderPaidMail::class);
    }

    public function test_wrong_amount_currency_or_payment_intent_cannot_pay_the_order(): void
    {
        $order = $this->createStripeOrder();

        foreach ([
            ['amount_total' => 39999],
            ['currency' => 'eur'],
            ['payment_status' => 'unpaid'],
        ] as $override) {
            $this->sendWebhook('checkout.session.completed', $this->paidSession($order, $override))
                ->assertOk();
        }

        $order->update(['stripe_payment_intent' => 'pi_existing']);
        $this->sendWebhook('checkout.session.completed', $this->paidSession($order))
            ->assertOk();

        $this->assertSame('pending', $order->fresh()->payment_status);
        Mail::assertNotSent(OrderPaidMail::class);
    }

    public function test_a_payment_intent_already_attached_to_another_order_cannot_pay_this_order(): void
    {
        $order = $this->createStripeOrder();
        $other = $this->createStripeOrder('paid', 'processing');

        $this->sendWebhook('checkout.session.completed', $this->paidSession($order, [
            'payment_intent' => $other->stripe_payment_intent,
        ]))->assertOk();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_shipping_is_included_in_the_verified_stripe_amount(): void
    {
        $order = $this->createStripeOrder();
        $order->update(['shipping_cost' => 25, 'total' => 425]);

        $this->sendWebhook('checkout.session.completed', $this->paidSession($order, [
            'amount_total' => 40000,
        ]))->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->sendWebhook('checkout.session.completed', $this->paidSession($order, [
            'amount_total' => 42500,
        ]))->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    private function paidSession(Order $order, array $override = []): array
    {
        return array_replace([
            'id' => $order->stripe_session_id,
            'payment_intent' => 'pi_new_'.$order->id,
            'payment_status' => 'paid',
            'currency' => 'ron',
            'amount_total' => 40000,
            'metadata' => ['order_id' => (string) $order->id],
        ], $override);
    }

    public function test_an_async_payment_failure_cancels_the_order_and_restores_stock_once(): void
    {
        $order = $this->createStripeOrder();
        $order->update(['stripe_session_id' => 'cs_test_async_failed']);
        $product = $order->items()->firstOrFail()->product;

        $payload = [
            'id' => 'cs_test_async_failed',
            'payment_status' => 'unpaid',
            'metadata' => ['order_id' => (string) $order->id],
        ];

        $this->sendWebhook('checkout.session.async_payment_failed', $payload)
            ->assertOk();
        $this->sendWebhook('checkout.session.async_payment_failed', $payload)
            ->assertOk();

        $order->refresh();
        $product->refresh();

        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(10, $product->stock_quantity);
        Mail::assertSent(OrderCancelledMail::class, 1);
    }

    public function test_a_partial_refund_does_not_cancel_the_order_or_restore_all_stock(): void
    {
        $order = $this->createStripeOrder('paid', 'processing');
        $product = $order->items()->firstOrFail()->product;

        ApiRequestor::setHttpClient(new class implements ClientInterface
        {
            public function request(
                $method,
                $absUrl,
                $headers,
                $params,
                $hasFile,
                $apiMode = 'v1',
                $maxNetworkRetries = null,
            ): array {
                return [
                    json_encode([
                        'object' => 'list',
                        'data' => [[
                            'id' => 're_partial',
                            'object' => 'refund',
                            'metadata' => [],
                        ]],
                    ]),
                    200,
                    [],
                ];
            }
        });

        $this->sendWebhook('charge.refunded', [
            'id' => 'ch_test_partial',
            'payment_intent' => $order->stripe_payment_intent,
            'currency' => 'ron',
            'amount' => 40_000,
            'amount_refunded' => 2_500,
        ])->assertOk();

        $order->refresh();
        $product->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame(6, $product->stock_quantity);
    }

    public function test_a_full_refund_cancels_the_order_and_restores_stock_once_when_redelivered(): void
    {
        $order = $this->createStripeOrder('paid', 'processing');
        $product = $order->items()->firstOrFail()->product;

        $this->fakeRefundList([
            [
                'id' => 're_full',
                'object' => 'refund',
                'metadata' => [],
            ],
        ]);

        $payload = [
            'id' => 'ch_test_full',
            'payment_intent' => $order->stripe_payment_intent,
            'currency' => 'ron',
            'amount' => 40_000,
            'amount_refunded' => 40_000,
        ];

        $this->sendWebhook('charge.refunded', $payload)->assertOk();
        $this->sendWebhook('charge.refunded', $payload)->assertOk();

        $order->refresh();
        $product->refresh();

        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(10, $product->stock_quantity);
        Mail::assertSent(OrderCancelledMail::class, 1);
    }

    public function test_a_refund_with_wrong_amount_or_currency_cannot_change_the_order(): void
    {
        $order = $this->createStripeOrder('paid', 'processing');

        foreach ([
            ['amount' => 39999, 'currency' => 'ron'],
            ['amount' => 40000, 'currency' => 'eur'],
        ] as $override) {
            $this->sendWebhook('charge.refunded', array_replace([
                'id' => 'ch_wrong',
                'payment_intent' => $order->stripe_payment_intent,
                'amount_refunded' => 40000,
            ], $override))->assertOk();
        }

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_a_refund_with_ambiguous_payment_intent_cannot_change_either_order(): void
    {
        $order = $this->createStripeOrder('paid', 'processing');
        $other = $this->createStripeOrder('paid', 'processing');
        $other->update(['stripe_payment_intent' => $order->stripe_payment_intent]);

        $this->sendWebhook('charge.refunded', [
            'id' => 'ch_ambiguous',
            'payment_intent' => $order->stripe_payment_intent,
            'currency' => 'ron',
            'amount' => 40000,
            'amount_refunded' => 40000,
        ])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $other->fresh()->payment_status);
    }

    public function test_return_refund_webhook_restores_stock_once_even_before_filament_action(): void
    {
        $order = $this->createStripeOrder('paid', 'delivered');
        $product = $order->items()->firstOrFail()->product;
        $return = ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => User::factory()->create()->id,
            'reason' => 'Test return',
            'status' => 'received',
            'requested_at' => now(),
        ]);

        $this->fakeRefundList([[
            'id' => 're_return',
            'object' => 'refund',
            'metadata' => [
                'return_request_id' => (string) $return->id,
                'order_id' => (string) $order->id,
            ],
        ]]);

        $payload = [
            'id' => 'ch_return',
            'payment_intent' => $order->stripe_payment_intent,
            'currency' => 'ron',
            'amount' => 40000,
            'amount_refunded' => 40000,
        ];

        $this->sendWebhook('charge.refunded', $payload)->assertOk();
        $this->sendWebhook('charge.refunded', $payload)->assertOk();
        $return->restoreStock();

        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertNotNull($return->fresh()->stock_restored_at);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_refund_metadata_for_a_different_order_cannot_change_a_return(): void
    {
        $order = $this->createStripeOrder('paid', 'delivered');
        $other = $this->createStripeOrder('paid', 'delivered');
        $return = ReturnRequest::create([
            'order_id' => $other->id,
            'user_id' => User::factory()->create()->id,
            'reason' => 'Wrong order',
            'status' => 'received',
            'requested_at' => now(),
        ]);

        $this->fakeRefundList([[
            'id' => 're_wrong',
            'object' => 'refund',
            'metadata' => [
                'return_request_id' => (string) $return->id,
                'order_id' => (string) $order->id,
            ],
        ]]);

        $this->sendWebhook('charge.refunded', [
            'id' => 'ch_wrong_return',
            'payment_intent' => $order->stripe_payment_intent,
            'currency' => 'ron',
            'amount' => 40000,
            'amount_refunded' => 40000,
        ])->assertOk();

        $this->assertSame('received', $return->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_a_manual_refund_is_rejected_while_a_return_is_active(): void
    {
        $order = $this->createStripeOrder('paid', 'delivered');

        ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => User::factory()->create()->id,
            'reason' => 'Test return',
            'status' => 'received',
            'requested_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('retur activ');

        app(StripeService::class)->refundPayment($order);
    }

    private function fakeRefundList(array $refunds): void
    {
        ApiRequestor::setHttpClient(new class($refunds) implements ClientInterface
        {
            public function __construct(private array $refunds) {}

            public function request(
                $method,
                $absUrl,
                $headers,
                $params,
                $hasFile,
                $apiMode = 'v1',
                $maxNetworkRetries = null,
            ): array {
                return [
                    json_encode([
                        'object' => 'list',
                        'data' => $this->refunds,
                    ]),
                    200,
                    [],
                ];
            }
        });
    }

    private function createStripeOrder(
        string $paymentStatus = 'pending',
        string $status = 'pending',
    ): Order {
        $category = Category::create([
            'name' => 'Webhook tests',
            'slug' => 'webhook-tests-'.uniqid(),
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produs webhook',
            'slug' => 'produs-webhook-'.uniqid(),
            'sku' => 'WEBHOOK-'.uniqid(),
            'purchase_price' => 50,
            'selling_price' => 100,
            'stock_quantity' => 6,
        ]);

        $order = Order::create([
            'order_number' => 'NOV-WEBHOOK-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'webhook@example.test',
            'phone' => '0700000000',
            'county' => 'Prahova',
            'city' => 'Ploiesti',
            'address' => 'Strada Test 1',
            'subtotal' => 400,
            'shipping_cost' => 0,
            'total' => 400,
            'payment_method' => 'stripe',
            'payment_status' => $paymentStatus,
            'status' => $status,
            'stripe_session_id' => 'cs_original_'.uniqid(),
            'stripe_payment_intent' => $paymentStatus === 'paid' ? 'pi_original_'.uniqid() : null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 100,
            'quantity' => 4,
            'total' => 400,
        ]);

        return $order;
    }

    private function sendWebhook(string $type, array $object)
    {
        $payload = json_encode([
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ]);

        $timestamp = time();
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            self::WEBHOOK_SECRET,
        );

        return $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}"],
            $payload,
        );
    }
}
