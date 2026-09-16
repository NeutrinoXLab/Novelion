<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pending_stripe_order_does_not_show_a_payment_success_message(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user, 'stripe', 'pending');

        $response = $this->actingAs($user)
            ->withSession(['cart' => ['product-1' => ['id' => 1, 'quantity' => 1]]])
            ->get(route('checkout.success', $order));

        $response->assertRedirect(route('my-orders.show', $order));
        $response->assertSessionHas('info');
        $response->assertSessionHas('cart');
    }

    public function test_a_confirmed_stripe_payment_clears_the_cart_and_shows_success(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user, 'stripe', 'paid');

        $response = $this->actingAs($user)
            ->withSession(['cart' => ['product-1' => ['id' => 1, 'quantity' => 1]]])
            ->get(route('checkout.success', $order));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('cart');
    }

    public function test_get_cancel_url_does_not_change_order_or_stock_reservation(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user, 'stripe', 'pending');

        $this->actingAs($user)->get(route('checkout.cancel', $order))
            ->assertRedirect(route('my-orders.show', $order));

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_post_cancel_is_owned_and_idempotent(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->createOrder($user, 'stripe', 'pending');

        $this->actingAs($other)->post(route('checkout.cancel-order', $order))
            ->assertForbidden();

        $this->actingAs($user)->post(route('checkout.cancel-order', $order))
            ->assertRedirect();
        $firstRestoredAt = $order->fresh()->stock_restored_at;
        $this->actingAs($user)->post(route('checkout.cancel-order', $order))
            ->assertRedirect();

        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertEquals($firstRestoredAt, $order->fresh()->stock_restored_at);
    }

    private function createOrder(
        User $user,
        string $paymentMethod,
        string $paymentStatus,
    ): Order {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => 'NOV-RETURN-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'phone' => '0700000000',
            'county' => 'Prahova',
            'city' => 'Ploiesti',
            'address' => 'Strada Test 1',
            'subtotal' => 100,
            'shipping_cost' => 0,
            'total' => 100,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'status' => $paymentStatus === 'paid' ? 'processing' : 'pending',
        ]);
    }
}
