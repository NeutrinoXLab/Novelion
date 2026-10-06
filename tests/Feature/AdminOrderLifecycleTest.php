<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Services\AdminOrderLifecycle;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\Refund;
use Tests\Concerns\UsesCommittedDatabase;
use Tests\TestCase;

class AdminOrderLifecycleTest extends TestCase
{
    use UsesCommittedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public static function reactivationTargets(): array
    {
        return [['processing'], ['shipped'], ['delivered'], ['cash_paid']];
    }

    #[DataProvider('reactivationTargets')]
    public function test_cancelled_is_terminal(string $target): void
    {
        [$order, $product] = $this->fixture();
        app(OrderService::class)->cancel($order);
        $this->rejectTransition($order, $target);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_repeat_cancellation_restores_once_and_records_one_receipt(): void
    {
        [$order, $product] = $this->fixture();
        $stale = $order->fresh();
        app(OrderService::class)->cancel($order);
        $marker = $order->fresh()->stock_restored_at;
        app(OrderService::class)->cancel($stale);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertEquals($marker, $order->fresh()->stock_restored_at);
        $this->assertSame(1, TransactionalEmail::where('kind', 'cancelled')->count());
    }

    public function test_payment_committed_after_admin_read_wins_over_cancellation(): void
    {
        [$order, $product] = $this->fixture('stripe');
        $stale = $order->fresh();
        $this->assertTrue(app(OrderService::class)->markAsPaid($order));
        $this->rejectTransition($stale, 'cancelled');
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_interleaved_paid_state_at_locked_read_boundary_is_rejected(): void
    {
        [$order, $product] = $this->fixture('stripe');
        $interleaved = false;
        DB::connection()->beforeExecuting(function ($sql) use ($order, &$interleaved): void {
            if (! $interleaved && str_starts_with($sql, 'select') && str_contains($sql, '"orders"')) {
                $interleaved = true;
                // Deterministic ordering at the read boundary, NOT a row-lock proof on SQLite.
                DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'paid', 'status' => 'processing']);
            }
        });
        $caught = null;
        try {
            app(OrderService::class)->cancel($order);
        } catch (\RuntimeException $exception) {
            $caught = $exception;
            $this->assertStringContainsString('plătită', $exception->getMessage());
        }
        $this->assertNotNull($caught, 'A paid order must not be cancelled.');
        $this->assertTrue($interleaved);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_cancellation_wins_over_later_payment_and_stale_form(): void
    {
        [$order, $product] = $this->fixture('stripe');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(EditOrder::class, ['record' => $order->id]);
        app(OrderService::class)->cancel($order);
        $this->assertFalse(app(OrderService::class)->markAsPaid($order));
        $page->fillForm(['status' => 'shipped', 'payment_status' => 'paid', 'payment_method' => 'cash', 'notes' => 'Note actualizate'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('stripe', $order->fresh()->payment_method);
        $this->assertSame('Note actualizate', $order->fresh()->notes);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_cancel_rolls_back_status_stock_and_receipt_when_marker_write_fails(): void
    {
        [$order, $product] = $this->fixture();
        DB::statement("CREATE TEMP TRIGGER f4_fail_marker BEFORE UPDATE OF stock_restored_at ON orders
            WHEN NEW.stock_restored_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'f4 marker failure'); END");
        try {
            app(OrderService::class)->cancel($order);
            $this->fail('Marker failure must abort the transaction.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('f4 marker failure', $exception->getMessage());
        } finally {
            DB::statement('DROP TRIGGER f4_fail_marker');
        }
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
        app(OrderService::class)->cancel($order);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_cash_actions_allow_fulfilment_and_later_collection_without_rewinding_delivery(): void
    {
        [$order, $product] = $this->fixture();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(EditOrder::class, ['record' => $order->id]);
        foreach (['processing', 'shipped', 'delivered', 'cash_paid', 'cash_paid'] as $action) {
            $page->callAction($action);
        }
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->delivered_at);
        $this->assertNotNull($order->fresh()->shipped_at);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::where('kind', 'paid')->count());
        $this->assertSame(1, TransactionalEmail::where('kind', 'delivered')->count());
    }

    public function test_cash_financial_state_cannot_be_forged_by_generic_save(): void
    {
        [$order] = $this->fixture();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->fillForm(['payment_status' => 'refunded', 'status' => 'delivered'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->refund_status);
    }

    public function test_stripe_requires_real_payment_and_cannot_use_cash_action(): void
    {
        [$order] = $this->fixture('stripe');
        $this->rejectTransition($order, 'processing');
        $this->rejectTransition($order, 'cash_paid');
        app(OrderService::class)->markAsPaid($order);
        app(AdminOrderLifecycle::class)->transition($order, 'shipped');
        app(AdminOrderLifecycle::class)->transition($order, 'delivered');
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_out_of_order_and_post_dispatch_cancellation_are_rejected(): void
    {
        [$order] = $this->fixture();
        $this->rejectTransition($order, 'delivered');
        app(AdminOrderLifecycle::class)->transition($order, 'processing');
        app(AdminOrderLifecycle::class)->transition($order, 'shipped');
        $this->rejectTransition($order, 'processing');
        $this->rejectTransition($order, 'cancelled');
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_restored_or_refunding_orders_cannot_be_fulfilled(): void
    {
        [$order] = $this->fixture();
        $order->update(['refund_status' => 'processing']);
        $this->rejectTransition($order, 'processing');
        $order->update(['refund_status' => null, 'stock_restored_at' => now()]);
        $this->rejectTransition($order, 'processing');
        $this->assertFalse(app(OrderService::class)->markAsPaid($order));
    }

    public function test_generic_admin_creation_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->assertFalse(OrderResource::canCreate());
        $this->get(OrderResource::getUrl('create'))->assertForbidden();
    }

    public function test_refund_intent_survives_lost_reply_and_blocks_fulfilment_until_retry(): void
    {
        [$order, $product] = $this->fixture('stripe');
        app(OrderService::class)->markAsPaid($order);
        $order->update(['stripe_payment_intent' => 'pi_f4']);
        $stripe = \Mockery::mock(StripeService::class);
        $stripe->shouldReceive('refundPayment')->once()->andThrow(new \RuntimeException('Lost HTTP reply'));
        $this->app->instance(StripeService::class, $stripe);
        $caught = null;
        try {
            app(AdminOrderLifecycle::class)->refund($order);
        } catch (\RuntimeException $exception) {
            $caught = $exception;
            $this->assertSame('Lost HTTP reply', $exception->getMessage());
        }
        $this->assertNotNull($caught, 'Expected lost reply.');
        $this->assertSame('initiated', $order->fresh()->refund_status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->rejectTransition($order, 'shipped');
        $retry = \Mockery::mock(StripeService::class);
        $retry->shouldReceive('refundPayment')->once()->andReturn(Refund::constructFrom(['id' => 're_retry']));
        $this->app->instance(StripeService::class, $retry);
        $this->assertSame('re_retry', app(AdminOrderLifecycle::class)->refund($order)->id);
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$order->id.':refund:initiated')->count());
    }

    public function test_same_refund_fixture_is_eligible_before_dispatch(): void
    {
        [$order] = $this->fixture('stripe');
        app(OrderService::class)->markAsPaid($order);
        $order->update(['stripe_payment_intent' => 'pi_f4']);
        $stripe = \Mockery::mock(StripeService::class);
        $stripe->shouldReceive('refundPayment')->once()->andReturn(Refund::constructFrom([
            'id' => 're_f4', 'payment_intent' => 'pi_f4', 'status' => 'pending', 'currency' => 'ron',
            'amount' => 20000, 'metadata' => ['order_id' => (string) $order->id]]));
        $this->app->instance(StripeService::class, $stripe);
        $this->assertSame('re_f4', app(AdminOrderLifecycle::class)->refund($order)->id);
        $this->assertSame('initiated', $order->fresh()->refund_status);
        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_stale_refund_after_dispatch_is_rejected_before_http(): void
    {
        [$order] = $this->fixture('stripe');
        app(OrderService::class)->markAsPaid($order);
        $order->update(['stripe_payment_intent' => 'pi_f4']);
        $stale = $order->fresh();
        app(AdminOrderLifecycle::class)->transition($order, 'shipped');
        $stripe = \Mockery::mock(StripeService::class);
        $stripe->shouldNotReceive('refundPayment');
        $this->app->instance(StripeService::class, $stripe);
        try {
            app(AdminOrderLifecycle::class)->refund($stale);
            $this->fail('Dispatched goods must use returns.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lifecycle', $exception->errors());
        }
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertNull($order->fresh()->refund_status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertNull($order->fresh()->stripe_refund_id);
        $this->assertNull($order->fresh()->refunded_at);
        $this->assertSame(8, $order->items()->first()->product->stock_quantity);
    }

    public static function cashReturnStates(): array
    {
        return [['requested'], ['approved'], ['received'], ['rejected']];
    }

    #[DataProvider('cashReturnStates')]
    public function test_cash_collection_after_return_keeps_delivery_and_stock(string $status): void
    {
        [$order, $product] = $this->fixture();
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        $order->update(['user_id' => $user->id, 'status' => 'delivered', 'delivered_at' => now()]);
        ReturnRequest::create(['order_id' => $order->id, 'user_id' => $user->id,
            'status' => $status, 'reason' => 'Test', 'requested_at' => now()]);
        $page = Livewire::test(EditOrder::class, ['record' => $order->id]);
        $page->callAction('cash_paid')->callAction('cash_paid');
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertSame(1, TransactionalEmail::where('kind', 'paid')->count());
    }

    public static function cashRefundStates(): array
    {
        return [['initiated'], ['processing'], ['completed']];
    }

    #[DataProvider('cashRefundStates')]
    public function test_cash_collection_cannot_overwrite_a_refund(string $state): void
    {
        [$order, $product] = $this->fixture();
        $user = User::factory()->create();
        $order->update(['user_id' => $user->id, 'status' => 'delivered']);
        $return = ReturnRequest::create(['order_id' => $order->id, 'user_id' => $user->id,
            'status' => $state === 'completed' ? 'refunded' : 'received',
            'refund_status' => $state, 'refund_method' => 'bank_transfer',
            'reason' => 'Test', 'requested_at' => now()]);
        $this->rejectTransition($order, 'cash_paid');
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame($state, $return->fresh()->refund_status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::where('kind', 'paid')->count());
    }

    public static function historicalDispatch(): array
    {
        return [['shipped_at'], ['delivered_at']];
    }

    #[DataProvider('historicalDispatch')]
    public function test_legacy_dispatch_refund_cannot_release_stock_through_cancel(string $timestamp): void
    {
        [$order, $product] = $this->fixture('stripe');
        $order->update(['status' => 'processing', 'payment_status' => 'paid',
            'stripe_payment_intent' => 'pi_history', $timestamp => now()]);
        $refund = \Stripe\StripeObject::constructFrom(['id' => 're_history', 'livemode' => false,
            'payment_intent' => 'pi_history', 'amount' => 20000, 'currency' => 'ron',
            'status' => 'succeeded', 'metadata' => ['order_id' => (string) $order->id]]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(EditOrder::class, ['record' => $order->id]);
        foreach ([1, 2] as $replay) {
            $this->assertSame('manual_review', app(\App\Services\StripeStateApplier::class)->refund($refund));
            $this->rejectTransition($order, 'cancelled');
            $page->callAction('cancelled');
            foreach (['cancel', 'restoreStock'] as $operation) {
                $caught = null;
                try {
                    app(OrderService::class)->$operation($order);
                } catch (\RuntimeException $exception) {
                    $caught = $exception;
                }
                $this->assertNotNull($caught, $operation.' must reject dispatch history.');
            }
        }
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('completed', $order->fresh()->refund_status);
        $this->assertNotNull($order->fresh()->getAttribute($timestamp));
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::where('kind', 'cancelled')->count());
        $this->assertSame(1, TransactionalEmail::where('event_key', 'order:'.$order->id.':refund:completed')->count());
    }

    #[DataProvider('historicalDispatch')]
    public function test_legacy_history_also_fences_admin_refund_fulfilment_and_failed_payment(string $timestamp): void
    {
        [$order, $product] = $this->fixture('stripe');
        $order->update(['status' => 'processing', 'payment_status' => 'paid',
            'stripe_payment_intent' => 'pi_history', $timestamp => now()]);
        $stripe = \Mockery::mock(StripeService::class);
        $stripe->shouldNotReceive('refundPayment');
        $this->app->instance(StripeService::class, $stripe);
        try {
            app(AdminOrderLifecycle::class)->refund($order);
            $this->fail('Legacy dispatch must use physical returns.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lifecycle', $exception->errors());
        }
        $this->rejectTransition($order, 'shipped');
        $this->rejectTransition($order, 'delivered');
        $order->update(['payment_status' => 'pending']);
        app(OrderService::class)->markAsFailed($order);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertNull($order->fresh()->refund_status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_unshipped_processing_cancellation_still_restores_once(): void
    {
        [$order, $product] = $this->fixture();
        app(AdminOrderLifecycle::class)->transition($order, 'processing');
        app(AdminOrderLifecycle::class)->transition($order, 'cancelled');
        $marker = $order->fresh()->stock_restored_at;
        app(AdminOrderLifecycle::class)->transition($order, 'cancelled');
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertEquals($marker, $order->fresh()->stock_restored_at);
        $this->assertSame(1, TransactionalEmail::where('kind', 'cancelled')->count());
    }

    public function test_actual_delivery_after_external_refund_preserves_finance_and_allows_return(): void
    {
        [$order, $product] = $this->fixture('stripe');
        $customer = User::factory()->create();
        $order->update(['user_id' => $customer->id, 'stripe_payment_intent' => 'pi_transit']);
        app(OrderService::class)->markAsPaid($order);
        app(AdminOrderLifecycle::class)->transition($order, 'shipped');
        $refund = \Stripe\StripeObject::constructFrom(['id' => 're_transit', 'livemode' => false,
            'payment_intent' => 'pi_transit', 'amount' => 20000, 'currency' => 'ron',
            'status' => 'succeeded', 'metadata' => ['order_id' => (string) $order->id]]);
        app(\App\Services\StripeStateApplier::class)->refund($refund);
        $before = $order->fresh()->only(['payment_status', 'refund_status', 'refunded_at',
            'stripe_refund_id', 'stripe_reconcile_result', 'shipped_at']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(EditOrder::class, ['record' => $order->id]);
        $page->callAction('delivered');
        $delivered = $order->fresh()->delivered_at;
        $this->travel(1)->hours();
        $page->callAction('delivered');
        $this->assertNotNull($delivered);
        $this->assertEquals($delivered, $order->fresh()->delivered_at);
        $this->assertEquals($before, $order->fresh()->only(array_keys($before)));
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertSame(1, TransactionalEmail::where('kind', 'delivered')->count());
        Livewire::test(\App\Filament\Resources\Orders\Pages\ViewOrder::class, ['record' => $order->id])
            ->assertSee('Rambursare financiara confirmata.');
        $this->actingAs($customer)->get(route('returns.create', $order))->assertOk();
        $this->post(route('returns.store', $order), ['type' => 'withdrawal',
            'items' => [$order->items()->first()->id => 1]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('requested', $order->returnRequests()->firstOrFail()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public static function invalidRefundDelivery(): array
    {
        return [['processing', true], ['cancelled', true], ['shipped', false]];
    }

    #[DataProvider('invalidRefundDelivery')]
    public function test_refund_delivery_exception_cannot_dispatch_or_reactivate(string $status, bool $history): void
    {
        [$order, $product] = $this->fixture('stripe');
        $order->update(['status' => $status, 'payment_status' => 'refunded', 'refund_status' => 'completed',
            'shipped_at' => $history ? now() : null]);
        $this->rejectTransition($order, 'delivered');
        $this->rejectTransition($order, 'shipped');
        $this->assertSame($status, $order->fresh()->status);
        $this->assertNull($order->fresh()->delivered_at);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    private function rejectTransition(Order $order, string $target): void
    {
        try {
            app(AdminOrderLifecycle::class)->transition($order, $target);
            $this->fail('Transition must be rejected: '.$target);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lifecycle', $exception->errors());
        }
    }

    private function fixture(string $method = 'cash'): array
    {
        $category = Category::create(['name' => 'F4', 'slug' => 'f4']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'F4', 'sku' => 'F4',
            'purchase_price' => 50, 'selling_price' => 100, 'stock_quantity' => 8, 'weight' => 1]);
        $order = Order::create(['order_number' => 'F4', 'first_name' => 'Test', 'last_name' => 'Client',
            'email' => 'f4@example.test', 'phone' => '0700000000', 'county' => 'Prahova', 'city' => 'Ploiesti',
            'address' => 'Test', 'subtotal' => 200, 'total' => 200, 'shipping_cost' => 0,
            'status' => 'pending', 'payment_status' => 'pending', 'payment_method' => $method]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => 'F4', 'quantity' => 2, 'price' => 100, 'total' => 200]);

        return [$order, $product];
    }
}
