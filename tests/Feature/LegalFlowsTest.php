<?php

namespace Tests\Feature;

use App\Mail\NewsletterConfirmationMail;
use App\Mail\ReturnRequestConfirmationMail;
use App\Models\Category;
use App\Models\Newsletter;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\StripeService;
use App\Services\WithdrawalDeadline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class LegalFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_partial_stripe_refund_stays_processing_until_processor_confirms(): void
    {
        Mail::fake();
        [$user, $order, $item] = $this->fixture();
        $order->update(['stripe_payment_intent' => 'pi_partial']);
        $this->actingAs($user)->post(route('returns.store', $order), ['type' => 'withdrawal', 'items' => [$item->id => 1]])->assertSessionHasNoErrors();
        $return = $order->returnRequests()->firstOrFail();
        $return->update(['status' => 'received']);
        config(['services.stripe.secret' => 'sk_test_fake']);
        $client = new class implements ClientInterface
        {
            public array $params = [];

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
            {
                $this->params = $params;

                return [json_encode(['id' => 're_partial', 'object' => 'refund', 'payment_intent' => 'pi_partial', 'status' => 'pending']), 200, []];
            }
        };
        ApiRequestor::setHttpClient($client);
        app(StripeService::class)->refundReturn($return);
        $this->assertSame(10000, (int) $client->params['amount']);
        $this->assertSame('processing', $return->fresh()->refund_status);
        $this->assertNull($return->fresh()->refunded_at);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_cash_withdrawal_records_bank_transfer_and_never_uses_stripe(): void
    {
        Mail::fake();
        [$user, $order, $item] = $this->fixture();
        $order->update(['payment_method' => 'cash']);
        $this->actingAs($user)->post(route('returns.store', $order), [
            'type' => 'withdrawal', 'items' => [$item->id => 1],
            'bank_iban' => 'RO49AAAA1B31007593840000', 'accept_bank_transfer' => '1',
        ])->assertSessionHasNoErrors();
        $return = $order->returnRequests()->firstOrFail();
        $this->assertSame('bank_transfer', $return->refund_method);
        $this->assertNotNull($return->bank_transfer_accepted_at);
        $this->expectException(\RuntimeException::class);
        app(StripeService::class)->refundReturn($return);
    }

    public function test_cash_nonconformity_can_add_bank_details_later_and_other_user_cannot(): void
    {
        Mail::fake();
        [$user, $order, $item] = $this->fixture();
        $order->update(['payment_method' => 'cash']);
        $this->actingAs($user)->post(route('returns.store', $order), [
            'type' => 'nonconformity', 'items' => [$item->id => 1], 'notes' => 'Produsul este defect.',
        ])->assertSessionHasNoErrors();
        $return = $order->returnRequests()->firstOrFail();
        $this->assertNull($return->bank_transfer_accepted_at);
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('returns.bank-details.store', $return), [
            'bank_iban' => 'RO49AAAA1B31007593840000', 'accept_bank_transfer' => '1',
        ])->assertForbidden();
        $this->actingAs($user)->post(route('returns.bank-details.store', $return), [
            'bank_iban' => 'RO49 AAAA 1B31 0075 9384 0000', 'accept_bank_transfer' => '1',
        ])->assertRedirect();
        $this->assertSame('RO49AAAA1B31007593840000', $return->fresh()->bank_iban);
        $this->assertNotNull($return->fresh()->bank_transfer_accepted_at);
    }

    public function test_one_of_ten_can_be_returned_without_reason_and_stock_is_restored_once(): void
    {
        Mail::fake();
        [$user, $order, $item, $product] = $this->fixture();
        $this->actingAs($user)->post(route('returns.store', $order), [
            'type' => 'withdrawal', 'items' => [$item->id => 1],
        ])->assertSessionHasNoErrors();
        $return = $order->returnRequests()->firstOrFail();
        $this->assertNull($return->reason);
        $this->assertSame(1, $return->items()->firstOrFail()->quantity);
        $this->assertSame(100.0, (float) $return->refund_amount);
        Mail::assertSent(ReturnRequestConfirmationMail::class);
        $return->restoreStock();
        $return->restoreStock();
        $this->assertSame(11, $product->fresh()->stock_quantity);
    }

    public function test_nonconformity_accepts_optional_photos_but_limits_count(): void
    {
        Mail::fake();
        Storage::fake('public');
        [$user, $order, $item] = $this->fixture();
        $this->actingAs($user)->post(route('returns.store', $order), [
            'type' => 'nonconformity', 'items' => [$item->id => 1], 'notes' => 'O unitate nu funcționează.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('nonconformity', $order->returnRequests()->firstOrFail()->type);
        $photos = array_fill(0, 4, UploadedFile::fake()->image('lamp.jpg'));
        $this->actingAs($user)->post(route('returns.store', $order), [
            'type' => 'nonconformity', 'items' => [$item->id => 1], 'notes' => 'Altă unitate nu funcționează.', 'photos' => $photos,
        ])->assertSessionHasErrors('photos');
        $this->actingAs($user)->post(route('returns.store', $order), [
            'type' => 'nonconformity', 'items' => [$item->id => 1], 'notes' => 'Altă unitate nu funcționează.',
            'photos' => [UploadedFile::fake()->image('mare.jpg')->size(5121)],
        ])->assertSessionHasErrors('photos.0');
    }

    public function test_newsletter_needs_confirmation_and_unsubscribe_does_not_reactivate(): void
    {
        Mail::fake();
        $this->post(route('newsletter.subscribe'), ['email' => 'buyer@example.test'])->assertRedirect();
        $subscriber = Newsletter::firstOrFail();
        $this->assertNull($subscriber->subscribed_at);
        Mail::assertSent(NewsletterConfirmationMail::class);
        $this->get(route('newsletter.confirm', $subscriber->confirmation_token))->assertOk();
        $this->assertNotNull($subscriber->fresh()->confirmed_at);
        $this->get(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))->assertOk();
        $this->post(route('newsletter.subscribe'), ['email' => 'buyer@example.test'])->assertRedirect();
        $this->assertNotNull($subscriber->fresh()->unsubscribed_at);
    }

    public function test_review_requires_purchase(): void
    {
        [$user, $order, $item, $product] = $this->fixture();
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('reviews.store', $product), ['rating' => 1, 'comment' => 'Comentariu critic valid'])->assertForbidden();
        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 1, 'comment' => 'Comentariu critic valid'])->assertRedirect();
        $this->assertDatabaseHas('reviews', ['product_id' => $product->id, 'rating' => 1, 'is_approved' => 0, 'is_verified_purchase' => 1]);
    }

    public function test_order_email_uses_shipping_address(): void
    {
        [, $order] = $this->fixture();
        $order->update([
            'shipping_first_name' => 'Livrare',
            'shipping_last_name' => 'Destinatar',
            'shipping_address' => 'Strada Livrare 99',
            'shipping_city' => 'Brașov',
            'shipping_county' => 'Brașov',
        ]);
        $html = view('emails.order-paid', ['order' => $order->load('items')])->render();
        $this->assertStringContainsString('Strada Livrare 99', $html);
        $this->assertStringNotContainsString('Strada Test 1', $html);
        $this->assertStringContainsString('Livrare', $html);
    }

    public function test_price_history_and_deadline_weekend(): void
    {
        [, , , $product] = $this->fixture();
        $product->update(['sale_price' => 90]);
        $this->assertSame(100.0, $product->referencePrice());
        $this->assertSame(2, $product->priceHistory()->count());
        $deadline = app(WithdrawalDeadline::class)->forDelivery(now()->setDate(2026, 9, 5));
        $this->assertSame('2026-09-21', $deadline->format('Y-m-d'));
        $easterDeadline = app(WithdrawalDeadline::class)->forDelivery(now()->setDate(2026, 3, 27));
        $this->assertSame('2026-04-14', $easterDeadline->format('Y-m-d'));
    }

    public function test_missing_weight_or_rate_blocks_checkout(): void
    {
        [$user, , , $product] = $this->fixture();
        $this->actingAs($user)->withSession(['cart' => [$product->id => ['id' => $product->id, 'quantity' => 1]]])
            ->get(route('checkout.index'))->assertRedirect(route('cart.index'));
        ShippingRate::create(['name' => 'Test', 'price' => 10, 'is_active' => true]);
        $product->update(['weight' => null]);
        $this->actingAs($user)->get(route('checkout.index'))->assertRedirect(route('cart.index'));
    }

    public function test_account_closure_preserves_pending_claim(): void
    {
        Mail::fake();
        [$user, $order, $item] = $this->fixture();
        $this->actingAs($user)->post(route('returns.store', $order), ['type' => 'nonconformity', 'items' => [$item->id => 1], 'notes' => 'Unitate defectă'])->assertSessionHasNoErrors();
        $id = $order->returnRequests()->firstOrFail()->id;
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        $this->assertDatabaseHas('returns', ['id' => $id, 'user_id' => $user->id]);
        $this->assertNotNull($user->fresh()->account_deleted_at);
    }

    private function fixture(): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid()]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Lampă', 'slug' => 'lamp-'.uniqid(), 'sku' => uniqid(), 'purchase_price' => 50, 'selling_price' => 100, 'stock_quantity' => 10, 'weight' => 1]);
        $order = Order::create(['user_id' => $user->id, 'order_number' => uniqid('NOV-'), 'first_name' => 'Test', 'last_name' => 'Client', 'email' => $user->email, 'phone' => '0750000000', 'county' => 'Prahova', 'city' => 'Ploiești', 'address' => 'Strada Test 1', 'subtotal' => 1000, 'shipping_cost' => 20, 'total' => 1020, 'payment_method' => 'stripe', 'payment_status' => 'paid', 'status' => 'delivered', 'delivered_at' => now()->subDay()]);
        $item = $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 100, 'quantity' => 10, 'total' => 1000]);

        return [$user, $order, $item, $product];
    }
}
