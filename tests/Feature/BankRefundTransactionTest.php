<?php

namespace Tests\Feature;

use App\Filament\Resources\ReturnRequests\Pages\EditReturnRequest;
use App\Mail\RefundStatusMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\UsesCommittedDatabase;
use Tests\TestCase;

class BankRefundTransactionTest extends TestCase
{
    use UsesCommittedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_filament_bank_refund_commits_status_stock_and_receipt_without_sending_smtp(): void
    {
        [$return, $product, $order] = $this->fixture();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(EditReturnRequest::class, ['record' => $return->getRouteKey()])
            ->callAction('bankRefund', data: ['refund_status' => 'completed'])
            ->assertHasNoActionErrors();

        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame('completed', $return->fresh()->refund_status);
        $this->assertNotNull($return->fresh()->refunded_at);
        $this->assertNotNull($return->fresh()->stock_restored_at);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, TransactionalEmail::count());
        $receipt = TransactionalEmail::firstOrFail();
        $this->assertSame($return->id, $receipt->return_request_id);
        $this->assertSame(['state' => 'completed', 'amount' => '200.00'], $receipt->payload);
        $this->assertNull($receipt->sent_at);
        Mail::assertNothingSent();

        $this->artisan('emails:deliver')->assertSuccessful();
        Mail::assertSent(RefundStatusMail::class, 1);
    }

    public function test_real_notification_insert_failure_rolls_back_status_and_leaves_stock_reserved(): void
    {
        [$return, $product] = $this->fixture();
        $operation = $this->operation($return);
        DB::statement("CREATE TEMP TRIGGER r1_fail_email BEFORE INSERT ON transactional_emails
            BEGIN SELECT RAISE(ABORT, 'R1 notification insert failure'); END");
        try {
            $operation(['refund_status' => 'completed']);
            $this->fail('The actual receipt INSERT must fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('R1 notification insert failure', $exception->getMessage());
            $this->assertStringContainsString('insert into', strtolower($exception->getSql()));
        } finally {
            DB::statement('DROP TRIGGER r1_fail_email');
        }

        $this->assertRolledBack($return, $product);
        // The same operation remains recoverable after fixing the DB failure.
        $operation(['refund_status' => 'completed']);
        $this->assertSame('completed', $return->fresh()->refund_status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::count());
        Mail::assertNothingSent();
    }

    public function test_stock_marker_failure_rolls_back_already_written_stock_status_and_receipt(): void
    {
        [$return, $product] = $this->fixture();
        $stockWriteExecuted = false;
        $receiptWriteExecuted = false;
        DB::listen(function ($query) use (&$stockWriteExecuted, &$receiptWriteExecuted): void {
            $stockWriteExecuted |= str_contains($query->sql, 'update "products"') && str_contains($query->sql, 'stock_quantity');
            $receiptWriteExecuted |= str_contains($query->sql, 'insert into "transactional_emails"');
        });
        DB::statement("CREATE TEMP TRIGGER r1_fail_stock_marker BEFORE UPDATE OF stock_restored_at ON returns
            WHEN NEW.stock_restored_at IS NOT NULL
            BEGIN SELECT RAISE(ABORT, 'R1 stock marker failure'); END");
        try {
            ($this->operation($return))(['refund_status' => 'completed']);
            $this->fail('The stock recovery marker write must fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('R1 stock marker failure', $exception->getMessage());
        } finally {
            DB::statement('DROP TRIGGER r1_fail_stock_marker');
        }

        $this->assertTrue((bool) $stockWriteExecuted);
        $this->assertTrue((bool) $receiptWriteExecuted);
        $this->assertRolledBack($return, $product);
    }

    public function test_repeated_completion_from_a_stale_page_preserves_stock_receipt_and_timestamps(): void
    {
        [$return, $product] = $this->fixture();
        $first = $this->operation($return);
        $stale = $this->operation($return->fresh());
        $first(['refund_status' => 'completed']);
        $refundedAt = $return->fresh()->refunded_at;
        $restoredAt = $return->fresh()->stock_restored_at;
        $this->travel(2)->minutes();
        $stale(['refund_status' => 'completed']);

        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame('completed', $return->fresh()->refund_status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertEquals($refundedAt, $return->fresh()->refunded_at);
        $this->assertEquals($restoredAt, $return->fresh()->stock_restored_at);
        $this->assertSame(1, TransactionalEmail::count());
        Mail::assertNothingSent();
    }

    public function test_stale_page_cannot_revert_a_completed_refund_to_processing(): void
    {
        [$return, $product] = $this->fixture();
        $stale = $this->operation($return->fresh());
        ($this->operation($return))(['refund_status' => 'completed']);
        try {
            $stale(['refund_status' => 'processing']);
            $this->fail('A stale action must not revert a completed refund.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('nu mai permite', $exception->getMessage());
        }
        $this->assertSame('completed', $return->fresh()->refund_status);
        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(1, TransactionalEmail::count());
    }

    private function assertRolledBack(ReturnRequest $return, Product $product): void
    {
        $this->assertSame('received', $return->fresh()->status);
        $this->assertSame('processing', $return->fresh()->refund_status);
        $this->assertNull($return->fresh()->refunded_at);
        $this->assertNull($return->fresh()->stock_restored_at);
        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertSame(0, TransactionalEmail::count());
        $this->assertSame(0, DB::transactionLevel());
        Mail::assertNothingSent();
    }

    private function operation(ReturnRequest $return): \Closure
    {
        // Execute the actual Filament callback; bypass UI visibility to model stale/recovery invocations.
        $page = new EditReturnRequest;
        $page->record = $return;
        $method = new \ReflectionMethod(EditReturnRequest::class, 'getHeaderActions');
        foreach ($method->invoke($page) as $action) {
            if ($action->getName() === 'bankRefund') {
                return $action->getActionFunction();
            }
        }
        throw new \LogicException('bankRefund action is missing.');
    }

    private function fixture(): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'R1', 'slug' => 'r1']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'R1 product', 'slug' => 'r1-product',
            'sku' => 'R1', 'purchase_price' => 50, 'selling_price' => 100, 'stock_quantity' => 6,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'order_number' => 'NOV-R1', 'first_name' => 'Test', 'last_name' => 'Client',
            'email' => 'r1@example.test', 'phone' => '0700000000', 'county' => 'Prahova', 'city' => 'Ploiesti',
            'address' => 'Test street', 'subtotal' => 400, 'shipping_cost' => 0, 'total' => 400,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => 'delivered',
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'price' => 100, 'quantity' => 4, 'total' => 400,
        ]);
        $return = ReturnRequest::create([
            'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'R1 test', 'status' => 'received',
            'refund_status' => 'processing', 'refund_method' => 'bank_transfer', 'refund_amount' => 200,
            'bank_transfer_accepted_at' => now(), 'requested_at' => now(),
        ]);
        $return->items()->create([
            'order_item_id' => $item->id, 'quantity' => 2, 'unit_price' => 100, 'line_refund_amount' => 200,
        ]);

        return [$return, $product, $order];
    }
}
