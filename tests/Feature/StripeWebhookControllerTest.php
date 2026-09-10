<?php

namespace Tests\Feature;

use App\Mail\OrderPaidMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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

    public function test_a_successful_payment_after_an_expired_session_does_not_leave_the_order_cancelled(): void
    {
        $order = $this->createStripeOrder();

        $this->sendWebhook('checkout.session.expired', [
            'id' => 'cs_test_sequence',
            'metadata' => ['order_id' => (string) $order->id],
        ])->assertOk();

        $this->sendWebhook('checkout.session.completed', [
            'id' => 'cs_test_sequence',
            'payment_intent' => 'pi_test_sequence',
            'payment_status' => 'paid',
            'metadata' => ['order_id' => (string) $order->id],
        ])->assertOk();

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertNull($order->stock_restored_at);
    }

    public function test_duplicate_completed_events_send_only_one_confirmation_email(): void
    {
        $order = $this->createStripeOrder();

        $payload = [
            'id' => 'cs_test_duplicate',
            'payment_intent' => 'pi_test_duplicate',
            'payment_status' => 'paid',
            'metadata' => ['order_id' => (string) $order->id],
        ];

        $this->sendWebhook('checkout.session.completed', $payload)->assertOk();
        $this->sendWebhook('checkout.session.completed', $payload)->assertOk();

        Mail::assertSent(OrderPaidMail::class, 1);
    }

    public function test_async_payment_success_marks_the_order_as_paid(): void
    {
        $order = $this->createStripeOrder();

        $this->sendWebhook('checkout.session.async_payment_succeeded', [
            'id' => 'cs_test_async',
            'payment_intent' => 'pi_test_async',
            'payment_status' => 'paid',
            'metadata' => ['order_id' => (string) $order->id],
        ])->assertOk();

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
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
            'amount' => 10_000,
            'amount_refunded' => 2_500,
        ])->assertOk();

        $order->refresh();
        $product->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame(6, $product->stock_quantity);
    }

    private function createStripeOrder(
        string $paymentStatus = 'pending',
        string $status = 'pending',
    ): Order
    {
        $category = Category::create([
            'name' => 'Webhook tests',
            'slug' => 'webhook-tests-' . uniqid(),
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produs webhook',
            'slug' => 'produs-webhook-' . uniqid(),
            'sku' => 'WEBHOOK-' . uniqid(),
            'purchase_price' => 50,
            'selling_price' => 100,
            'stock_quantity' => 6,
        ]);

        $order = Order::create([
            'order_number' => 'NOV-WEBHOOK-' . uniqid(),
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
            'stripe_session_id' => 'cs_original_' . uniqid(),
            'stripe_payment_intent' => 'pi_original_' . uniqid(),
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
            'id' => 'evt_' . uniqid(),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ]);

        $timestamp = time();
        $signature = hash_hmac(
            'sha256',
            $timestamp . '.' . $payload,
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
