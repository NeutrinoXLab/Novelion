<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class OrderAdminRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.stripe.secret' => 'sk_test_fake']);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_admin_cancellation_does_not_overwrite_refunded_payment_with_stale_form_state(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Refund test', 'slug' => 'refund-test']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Refund item',
            'slug' => 'refund-item',
            'sku' => 'REFUND-ITEM',
            'purchase_price' => 50,
            'selling_price' => 100,
            'stock_quantity' => 6,
        ]);
        $order = Order::create([
            'order_number' => 'NOV-ADMIN-REFUND',
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => 'client@example.test',
            'phone' => '0700000000',
            'county' => 'Prahova',
            'city' => 'Ploiesti',
            'address' => 'Test street',
            'subtotal' => 400,
            'shipping_cost' => 0,
            'total' => 400,
            'payment_method' => 'stripe',
            'payment_status' => 'paid',
            'status' => 'processing',
            'stripe_payment_intent' => 'pi_admin_test',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 100,
            'quantity' => 4,
            'total' => 400,
        ]);

        $client = new class implements ClientInterface
        {
            public int $requests = 0;

            public function request(
                $method,
                $absUrl,
                $headers,
                $params,
                $hasFile,
                $apiMode = 'v1',
                $maxNetworkRetries = null,
            ): array {
                $this->requests++;

                return [json_encode([
                    'id' => 're_admin_test',
                    'object' => 'refund',
                    'payment_intent' => 'pi_admin_test',
                    'status' => 'succeeded',
                ]), 200, []];
            }
        };
        ApiRequestor::setHttpClient($client);

        $this->actingAs($admin);
        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['status' => 'cancelled'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(1, $client->requests);
    }
}
