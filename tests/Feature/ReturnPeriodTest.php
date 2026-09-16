<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_return_can_be_requested_within_fourteen_days_of_delivery(): void
    {
        $user = User::factory()->create();
        $order = $this->createDeliveredOrder($user, now()->subDays(13));

        $this->actingAs($user)
            ->get(route('returns.create', $order))
            ->assertOk();
    }

    public function test_a_return_cannot_be_requested_after_fourteen_days_of_delivery(): void
    {
        $user = User::factory()->create();
        $order = $this->createDeliveredOrder($user, now()->subDays(15));

        $this->actingAs($user)
            ->get(route('returns.create', $order))
            ->assertOk(); // Reclamația pentru neconformitate rămâne accesibilă și după termenul de retragere.
    }

    private function createDeliveredOrder(User $user, $deliveredAt): Order
    {
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
            'payment_method' => 'stripe',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'delivered_at' => $deliveredAt,
        ]);
    }
}
