<?php

namespace App\Services;

use App\Models\Order;
use Stripe\Checkout\Session;
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
}