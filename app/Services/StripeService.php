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
            ),

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
        Stripe::setApiKey(config('services.stripe.secret'));

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

        $refund = Refund::create([
            'payment_intent' => $order->stripe_payment_intent,
        ]);

        $order->update([
            'payment_status' => 'refunded',
            'status' => 'cancelled',
        ]);

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

        /*
         * Cerem refund-ul integral pentru Payment Intent.
         *
         * Atașăm ID-ul returului în metadata Stripe pentru ca webhook-ul
         * charge.refunded să poată identifica faptul că este un refund
         * pentru retur și să nu restaureze stocul încă o dată.
         */
        $refund = Refund::create(
            [
                'payment_intent' => $order->stripe_payment_intent,

                'metadata' => [
                    'return_request_id' => (string) $return->id,
                    'order_id' => (string) $order->id,
                ],
            ],
            [
                'idempotency_key' => 'return-refund-' . $return->id,
            ]
        );

        /*
         * Salvăm imediat ID-ul Stripe și momentul rambursării.
         */
        $return->update([
            'stripe_refund_id' => $refund->id,
            'refunded_at' => now(),
            'status' => 'refunded',
        ]);

        return $refund;
    }
}