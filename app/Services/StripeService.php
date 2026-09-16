<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use Stripe\Checkout\Session;
use Stripe\Refund;
use Stripe\Stripe;

class StripeService
{
    /**
     * Creează sesiunea Stripe Checkout.
     */
    public function createCheckoutSession(Order $order): Session
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $lineItems = [];

        /*
        |--------------------------------------------------------------------------
        | PRODUSE
        |--------------------------------------------------------------------------
        */

        foreach ($order->items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'ron',

                    'product_data' => [
                        'name' => $item->product_name,
                    ],

                    'unit_amount' => (int) round($item->price * 100),
                ],

                'quantity' => $item->quantity,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSPORT
        |--------------------------------------------------------------------------
        |
        | Transportul este adăugat separat pentru ca suma trimisă către
        | Stripe să fie identică cu totalul comenzii din Novelion.
        |
        */

        if ((float) $order->shipping_cost > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'ron',

                    'product_data' => [
                        'name' => 'Transport',
                    ],

                    'unit_amount' => (int) round(
                        $order->shipping_cost * 100
                    ),
                ],

                'quantity' => 1,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | STRIPE CHECKOUT
        |--------------------------------------------------------------------------
        */

        $session = Session::create([
            'mode' => 'payment',

            'line_items' => $lineItems,

            'success_url' => route(
                'checkout.success',
                ['order' => $order->id]
            ).'?session_id={CHECKOUT_SESSION_ID}',

            'cancel_url' => route(
                'checkout.cancel',
                ['order' => $order->id]
            ),

            'metadata' => [
                'order_id' => $order->id,
            ],
        ]);

        $order->update([
            'stripe_session_id' => $session->id,
        ]);

        return $session;
    }

    /**
     * Rambursează plata Stripe pentru o comandă.
     */
    public function refundPayment(Order $order): Refund
    {
        if ($order->payment_method !== 'stripe') {
            throw new \RuntimeException('Numai comenzile plătite prin Stripe pot fi rambursate prin Stripe.');
        }
        Stripe::setApiKey(config('services.stripe.secret'));

        if (! $order->stripe_payment_intent) {
            throw new \RuntimeException(
                'Comanda nu are un Payment Intent Stripe.'
            );
        }

        if ($order->payment_status === 'refunded' || $order->stripe_refund_id) {
            throw new \RuntimeException(
                'Plata acestei comenzi a fost deja rambursată.'
            );
        }

        if ($order->returnRequests()->whereNotIn('status', ['rejected'])->exists()) {
            throw new \RuntimeException(
                'Comanda are un retur activ sau rambursat.'
            );
        }

        $refund = Refund::create(
            ['payment_intent' => $order->stripe_payment_intent,
                'metadata' => ['order_id' => (string) $order->id]],
            ['idempotency_key' => 'order-refund-'.$order->id]
        );

        $order->update([
            'stripe_refund_id' => $refund->id,
            'refund_status' => ($refund->status ?? null) === 'succeeded' ? 'completed' : 'processing',
            'refunded_at' => ($refund->status ?? null) === 'succeeded' ? now() : null,
        ]);

        if (($refund->status ?? null) === 'succeeded') {
            $order->update(['payment_status' => 'refunded', 'status' => 'cancelled']);
            app(OrderService::class)->restoreStock($order);
        }

        return $refund;
    }

    /**
     * Rambursează plata Stripe pentru un retur primit.
     */
    public function refundReturn(ReturnRequest $return): Refund
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $return->loadMissing('order');

        $order = $return->order;

        if ($order->payment_method !== 'stripe') {
            throw new \RuntimeException('Comanda ramburs se restituie prin transfer bancar, nu prin Stripe.');
        }

        if ($return->status !== 'received') {
            throw new \RuntimeException(
                'Returul poate fi rambursat doar după ce a fost primit.'
            );
        }

        /*
         * Dacă există deja un refund Stripe, nu mai trimitem
         * o a doua cerere către Stripe.
         */
        if ($return->stripe_refund_id) {
            throw new \RuntimeException(
                'Acest retur are deja un refund Stripe înregistrat.'
            );
        }

        if (! $order->stripe_payment_intent) {
            throw new \RuntimeException(
                'Comanda nu are un Payment Intent Stripe.'
            );
        }

        if ($order->payment_status === 'refunded') {
            throw new \RuntimeException(
                'Plata acestei comenzi a fost deja rambursată.'
            );
        }

        if (! $return->refund_amount || (float) $return->refund_amount <= 0) {
            throw new \RuntimeException('Returul nu are o sumă validă de rambursat.');
        }

        $refund = Refund::create(
            [
                'payment_intent' => $order->stripe_payment_intent,
                'amount' => (int) round((float) $return->refund_amount * 100),

                'metadata' => [
                    'return_request_id' => (string) $return->id,
                    'order_id' => (string) $order->id,
                ],
            ],
            [
                'idempotency_key' => 'return-refund-'.$return->id,
            ]
        );

        /*
         * Cererea creată nu înseamnă că procesatorul a finalizat refund-ul.
         */
        $return->update([
            'stripe_refund_id' => $refund->id,
            'refund_status' => in_array($refund->status ?? null, ['succeeded'], true) ? 'completed' : 'processing',
            'status' => ($refund->status ?? null) === 'succeeded' ? 'refunded' : 'received',
            'refunded_at' => ($refund->status ?? null) === 'succeeded' ? now() : null,
        ]);

        if (($refund->status ?? null) === 'succeeded') {
            $return->restoreStock();
        }

        return $refund;
    }
}
