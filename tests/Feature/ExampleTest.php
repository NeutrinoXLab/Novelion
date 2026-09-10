<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Stocul unui retur este restaurat o singură dată.
     */
    public function test_return_stock_is_restored_only_once(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $category = Category::create([
            'name' => 'Test',
            'slug' => 'test',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produs test',
            'slug' => 'produs-test',
            'sku' => 'TEST-001',
            'purchase_price' => 50,
            'selling_price' => 100,
            'stock_quantity' => 10,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'TEST-ORDER-001',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '0700000000',
            'county' => 'Prahova',
            'city' => 'Ploiesti',
            'address' => 'Test 1',
            'subtotal' => 400,
            'shipping_cost' => 0,
            'total' => 400,
            'payment_method' => 'stripe',
            'payment_status' => 'paid',
            'status' => 'delivered',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 100,
            'quantity' => 4,
            'total' => 400,
        ]);

        $return = ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'reason' => 'Produs test',
            'status' => 'received',
            'requested_at' => now(),
            'received_at' => now(),
        ]);

        $return->restoreStock();

        $product->refresh();

        $this->assertSame(14, $product->stock_quantity);
        $this->assertNotNull($return->fresh()->stock_restored_at);

        $return->restoreStock();

        $product->refresh();

        $this->assertSame(14, $product->stock_quantity);
    }
}