<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\AdminProductStock;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminProductStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_form_editing_name_preserves_checkout_reservation_and_refreshes_baseline(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $this->checkout($product);
        $page->fillForm(['name' => 'New name'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertSame('New name', $product->fresh()->name);
        $page->assertSet('originalStock', 9)->assertSet('data.stock_quantity', 9);
        $page->fillForm(['stock_quantity' => 11])->call('save')->assertHasNoFormErrors();
        $this->assertSame(11, $product->fresh()->stock_quantity);
    }

    public function test_stale_explicit_adjustment_is_rejected_without_partial_metadata_save(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $this->checkout($product);
        $page->fillForm(['stock_quantity' => 12, 'name' => 'Must not persist'])
            ->call('save')->assertHasFormErrors(['stock_quantity']);
        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertSame('F4 product', $product->fresh()->name);
    }

    public function test_partial_refresh_cannot_advance_stock_baseline_without_the_field(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $this->checkout($product);
        $page->call('refreshFormData', ['name'])
            ->assertSet('originalStock', 10)->assertSet('data.stock_quantity', 10)
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    public function test_explicit_stock_refresh_updates_field_and_baseline_together(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $this->checkout($product);
        $page->call('refreshFormData', ['stock_quantity'])
            ->assertSet('originalStock', 9)->assertSet('data.stock_quantity', 9)
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    public function test_two_admin_forms_cannot_silently_overwrite_an_adjustment(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $first = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $second = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $first->fillForm(['stock_quantity' => 15])->call('save')->assertHasNoFormErrors();
        $second->call('refreshFormData', ['name'])->fillForm(['stock_quantity' => 8])
            ->call('save')->assertHasFormErrors(['stock_quantity']);
        $this->assertSame(15, $product->fresh()->stock_quantity);
    }

    public static function invalidValues(): array
    {
        return [
            ['stock_quantity', -1], ['stock_quantity', 1.5], ['stock_quantity', 2147483648],
            ['selling_price', -1], ['selling_price', 0], ['selling_price', 'oops'],
            ['selling_price', '1.001'], ['selling_price', 100000000],
            ['sale_price', -1], ['sale_price', 0], ['sale_price', '1.001'],
            ['purchase_price', -1],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_model_rejects_invalid_commercial_values(string $field, mixed $value): void
    {
        $product = $this->product();
        $original = $product->getRawOriginal($field);
        try {
            $product->update([$field => $value]);
            $this->fail('Invalid value accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertSame($original, $product->fresh()->getRawOriginal($field));
    }

    public function test_admin_form_rejects_fractional_stock_and_negative_price(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['stock_quantity' => 1.5, 'selling_price' => -1])
            ->call('save')->assertHasFormErrors(['stock_quantity', 'selling_price']);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public static function invalidPersistedValues(): array
    {
        return [['selling_price', -1], ['stock_quantity', -1], ['stock_quantity', 1.5], ['sale_price', 0]];
    }

    #[DataProvider('invalidPersistedValues')]
    public function test_order_service_rejects_invalid_persisted_values_even_without_model_validation(string $field, mixed $value): void
    {
        $product = $this->product();
        DB::table('products')->where('id', $product->id)->update([$field => $value]);
        try {
            $this->checkout($product);
            $this->fail('Invalid persisted price accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertSame(0, Order::count());
        $this->assertEquals($field === 'stock_quantity' ? $value : 10, $product->fresh()->stock_quantity);
    }

    public function test_order_service_rejects_total_overflow_before_reserving_stock(): void
    {
        $product = $this->product();
        $product->update(['selling_price' => 99999999.99, 'stock_quantity' => 2147483647]);
        $caught = null;
        try {
            $this->checkout($product, 2147483647);
        } catch (\RuntimeException $exception) {
            $caught = $exception;
            $this->assertStringContainsString('Totalul', $exception->getMessage());
        }
        $this->assertNotNull($caught, 'Overflow accepted.');
        $this->assertSame(0, Order::count());
        $this->assertSame(2147483647, $product->fresh()->stock_quantity);
    }

    public function test_valid_zero_stock_zero_purchase_cost_and_positive_sale_are_supported(): void
    {
        $product = $this->product();
        app(AdminProductStock::class)->save($product, ['stock_quantity' => 0, 'purchase_price' => 0,
            'selling_price' => 100, 'sale_price' => 90.50], 10);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame('90.50', $product->fresh()->sale_price);
        $product->refresh()->update(['stock_quantity' => 2]);
        $order = $this->checkout($product);
        $this->assertSame(90.5, (float) $order->subtotal);
        $this->assertSame(1, $product->fresh()->stock_quantity);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'F4', 'slug' => 'f4']);

        return Product::create(['category_id' => $category->id, 'name' => 'F4 product', 'sku' => 'F4',
            'purchase_price' => 50, 'selling_price' => 100, 'stock_quantity' => 10, 'weight' => 1,
            'manufacturer_name' => 'Manufacturer', 'manufacturer_contact' => 'Address', 'model_identifier' => 'F4']);
    }

    private function checkout(Product $product, int $quantity = 1): Order
    {
        ShippingRate::firstOrCreate(['name' => 'Test'], ['price' => 0, 'is_active' => true]);
        session()->put('cart', [$product->id => ['id' => $product->id, 'quantity' => $quantity]]);

        return app(OrderService::class)->create([
            'first_name' => 'Test', 'last_name' => 'Client', 'email' => 'f4@example.test', 'phone' => '0700000000',
            'county' => 'Prahova', 'city' => 'Ploiesti', 'address' => 'Test', 'payment_method' => 'cash',
            'shipping_first_name' => 'Test', 'shipping_last_name' => 'Client', 'shipping_phone' => '0700000000',
            'shipping_county' => 'Prahova', 'shipping_city' => 'Ploiesti', 'shipping_address' => 'Test', 'shipping_postal_code' => null,
        ], app(CartService::class));
    }
}
