<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReturnRequestLockOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_inverse_request_order_has_identical_ordered_lock_query_and_correct_quantities(): void
    {
        [$order, $items] = $this->fixture();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, '"order_items"')
                && str_contains($query->sql, '"order_items"."id" in (')
                && str_contains($query->sql, '"order_items"."order_id" = ?')) {
                $queries[] = [$query->sql, $query->bindings];
            }
        });
        foreach ([[$items[0]->id => 1, $items[1]->id => 2], [$items[1]->id => 2, $items[0]->id => 1]] as $selected) {
            $this->post(route('returns.store', $order), ['type' => 'withdrawal', 'items' => $selected])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertCount(2, $queries, json_encode($queries));
        $this->assertSame($queries[0], $queries[1]);
        $this->assertStringContainsString('order by "id" asc', $queries[0][0]);
        $this->assertSame(2, ReturnRequest::count());
        foreach (ReturnRequest::all() as $return) {
            $this->assertSame([$items[0]->id, $items[1]->id], $return->items()->orderBy('id')->pluck('order_item_id')->all());
            $this->assertSame([1, 2], $return->items()->orderBy('id')->pluck('quantity')->all());
            $this->assertSame('300.00', $return->refund_amount);
            $this->assertNull($return->stock_restored_at);
        }
    }

    public static function invalidSelections(): array
    {
        return [['missing'], ['foreign'], ['alias'], ['fractional_id'], ['excess_quantity']];
    }

    #[DataProvider('invalidSelections')]
    public function test_invalid_selection_rolls_back_whole_request(string $case): void
    {
        [$order, $items] = $this->fixture();
        $foreign = $order->replicate();
        $foreign->order_number = 'foreign';
        $foreign->save();
        $foreignItem = $foreign->items()->create(['product_id' => $items[0]->product_id,
            'product_name' => 'Foreign', 'quantity' => 5, 'price' => 100, 'total' => 500]);
        $selected = match ($case) {
            'missing' => [$items[0]->id => 1, 999999 => 1],
            'foreign' => [$items[0]->id => 1, $foreignItem->id => 1],
            'alias' => [$items[0]->id => 1, '0'.$items[0]->id => 1],
            'fractional_id' => [$items[0]->id.'.0' => 1],
            'excess_quantity' => [$items[0]->id => 1, $items[1]->id => 6],
        };
        $this->post(route('returns.store', $order), ['type' => 'withdrawal', 'items' => $selected])
            ->assertSessionHasErrors();
        $this->assertSame(0, ReturnRequest::count());
        $this->assertSame(0, ReturnItem::count());
        $this->assertSame(5, $items[0]->product->fresh()->stock_quantity);
        Mail::assertNothingSent();
    }

    public function test_duplicate_map_keys_cannot_create_duplicate_return_positions(): void
    {
        [$order, $items] = $this->fixture();
        // PHP/HTTP associative maps have one value per canonical key; aliases are separately rejected.
        $this->post(route('returns.store', $order), ['type' => 'withdrawal',
            'items' => [$items[0]->id => 1, $items[0]->id => 2]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, ReturnItem::count());
        $this->assertSame(2, ReturnItem::firstOrFail()->quantity);
        $this->assertSame(5, $items[0]->product->fresh()->stock_quantity);
    }

    private function fixture(): array
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = Category::create(['name' => 'Return locks', 'slug' => 'return-locks']);
        $order = Order::create(['user_id' => $user->id, 'order_number' => 'locks', 'first_name' => 'Test',
            'last_name' => 'Client', 'email' => $user->email, 'phone' => '0700000000', 'county' => 'Test',
            'city' => 'Test', 'address' => 'Test', 'subtotal' => 1000, 'total' => 1000, 'shipping_cost' => 0,
            'payment_method' => 'stripe', 'payment_status' => 'paid', 'status' => 'delivered', 'delivered_at' => now()]);
        $items = [];
        foreach ([1, 2] as $id) {
            $product = Product::create(['category_id' => $category->id, 'name' => 'Product '.$id,
                'sku' => 'LOCK-'.$id, 'purchase_price' => 50, 'selling_price' => 100, 'stock_quantity' => 5]);
            $items[] = $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name,
                'quantity' => 5, 'price' => 100, 'total' => 500]);
        }

        return [$order, $items];
    }
}
