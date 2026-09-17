<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_category_can_be_deleted_but_category_with_products_is_protected(): void
    {
        $empty = Category::create(['name' => 'Goală', 'slug' => 'goala']);
        $empty->delete();
        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);

        $category = Category::create(['name' => 'Iluminat', 'slug' => 'iluminat']);
        $product = $this->product($category);

        try {
            $category->delete();
            $this->fail('Categoria cu produse a fost ștearsă.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('produse asociate', $exception->getMessage());
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_without_order_history_can_be_deleted_with_disposable_dependencies(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/test.jpg', 'image');
        $product = $this->product(Category::create(['name' => 'Iluminat', 'slug' => 'iluminat']));
        ProductImage::create(['product_id' => $product->id, 'image_path' => 'products/test.jpg', 'is_primary' => true]);

        $product->delete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        Storage::disk('public')->assertMissing('products/test.jpg');
    }

    public function test_product_used_by_historical_order_cannot_be_deleted(): void
    {
        $product = $this->product(Category::create(['name' => 'Iluminat', 'slug' => 'iluminat']));
        $order = Order::create([
            'order_number' => 'HIST-001',
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => 'client@example.com',
            'phone' => '0700000000',
            'county' => 'Prahova',
            'city' => 'Ploiești',
            'address' => 'Str. Test 1',
            'subtotal' => 100,
            'shipping_cost' => 0,
            'total' => 100,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'delivered',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('comenzi istorice');

        try {
            $product->delete();
        } finally {
            $this->assertDatabaseHas('products', ['id' => $product->id]);
            $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $product->id]);
        }
    }

    public function test_product_slug_is_url_safe_unique_and_stable_when_name_changes(): void
    {
        $category = Category::create(['name' => 'Iluminat', 'slug' => 'iluminat']);
        $first = $this->product($category, 'Lampă solară LED exterior RGB alb cald IP65 6V/2W', null, 'AR-PL-85');
        $second = $this->product($category, 'Lampă solară LED exterior RGB alb cald IP65 6V/2W', null, 'AR-PL-86');

        $this->assertSame('lampa-solara-led-exterior-rgb-alb-cald-ip65-6v-2w', $first->slug);
        $this->assertSame('lampa-solara-led-exterior-rgb-alb-cald-ip65-6v-2w-2', $second->slug);

        $first->update(['name' => 'Nume schimbat accidental']);
        $this->assertSame('lampa-solara-led-exterior-rgb-alb-cald-ip65-6v-2w', $first->fresh()->slug);
    }

    public function test_product_and_category_admin_pages_render_with_delete_actions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Iluminat', 'slug' => 'iluminat']);
        $product = $this->product($category);

        $this->actingAs($admin)->get('/admin/categories')->assertOk();
        $this->actingAs($admin)->get('/admin/categories/'.$category->slug.'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/products')->assertOk();
        $this->actingAs($admin)->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSeeText('Adresă URL (generată automat)');
    }

    private function product(Category $category, string $name = 'Produs test', ?string $slug = null, ?string $sku = null): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku ?? 'SKU-'.uniqid(),
            'purchase_price' => 50,
            'selling_price' => 100,
            'stock_quantity' => 1,
        ]);
    }
}
