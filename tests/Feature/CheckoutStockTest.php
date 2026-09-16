<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ShippingRate::create(['name' => 'Tarif test', 'price' => 0, 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_duplicate_cart_entries_cannot_reserve_more_than_available_stock(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Stock test', 'slug' => 'stock-test']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Stock item',
            'slug' => 'stock-item',
            'sku' => 'STOCK-ITEM',
            'purchase_price' => 50,
            'selling_price' => 100,
            'stock_quantity' => 6,
            'weight' => 1,
        ]);

        $this->actingAs($user)
            ->withSession(['cart' => [
                'first' => ['id' => $product->id, 'quantity' => 4],
                'second' => ['id' => $product->id, 'quantity' => 4],
            ]])
            ->post(route('checkout.store'), $this->checkoutData($user))
            ->assertSessionHas('error');

        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertSame(0, Order::count());
    }

    public function test_duplicate_cart_entries_with_enough_stock_create_one_item_and_one_reservation(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Stock test', 'slug' => 'stock-test']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Stock item',
            'slug' => 'stock-item',
            'sku' => 'STOCK-ITEM',
            'purchase_price' => 50,
            'selling_price' => 100,
            'stock_quantity' => 10,
            'weight' => 1,
        ]);

        $this->actingAs($user)
            ->withSession(['cart' => [
                'first' => ['id' => $product->id, 'quantity' => 2],
                'second' => ['id' => $product->id, 'quantity' => 2],
            ]])
            ->post(route('checkout.store'), $this->checkoutData($user))
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertSame(400.0, (float) $order->subtotal);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(4, $order->items()->firstOrFail()->quantity);
    }

    public function test_optional_postal_code_and_company_registration_can_be_omitted(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Company test', 'slug' => 'company-test']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Company item',
            'slug' => 'company-item',
            'sku' => 'COMPANY-ITEM',
            'purchase_price' => 50,
            'selling_price' => 100,
            'stock_quantity' => 2,
            'weight' => 1,
        ]);

        $data = array_merge($this->checkoutData($user), [
            'customer_type' => 'company',
            'company_name' => 'Test Company',
            'company_vat' => 'RO123',
            'company_address' => 'Company street',
            'company_city' => 'Ploiesti',
            'company_county' => 'Prahova',
        ]);

        $this->actingAs($user)
            ->withSession(['cart' => [$product->id => ['id' => $product->id, 'quantity' => 1]]])
            ->post(route('checkout.store'), $data)
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertNull($order->company_registration);
        $this->assertNull($order->postal_code);
        $this->assertNull($order->shipping_postal_code);
    }

    private function checkoutData(User $user): array
    {
        return [
            'customer_type' => 'individual',
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => $user->email,
            'phone' => '0700000000',
            'county' => 'Prahova',
            'city' => 'Ploiesti',
            'address' => 'Test street',
            'payment_method' => 'cash',
        ];
    }
}
