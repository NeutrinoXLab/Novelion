<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;
use Stripe\ApiRequestor;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\HttpClient\CurlClient;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class StripeService
{
    /**
     * Creează sesiunea Stripe Checkout.
     */
    public function createCheckoutSession(Order $order, ?array $parameters = null, ?string $idempotencyKey = null): Session
    {
        app(StripeModePolicy::class)->livemode();
        Stripe::setApiKey(config('services.stripe.secret'));

        $session = Session::create($parameters ?? $this->checkoutParameters($order), [
            'idempotency_key' => $idempotencyKey ?? 'novelion-order-checkout-'.$order->id,
        ]);
        $order->update(['stripe_session_id' => $session->id]);

        return $session;
    }

    public function checkoutParameters(Order $order): array
    {
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

        return [
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
        ];
    }

    /**
     * Rambursează plata Stripe pentru o comandă.
     */
    public function refundPayment(Order $order): Refund
    {
        app(StripeModePolicy::class)->livemode();
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

        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Stripe refund HTTP cannot run inside a DB transaction.');
        }

        // A lost POST reply must not leave recovery waiting for the weekly stable scan.
        Order::query()->whereKey($order->id)->update(['stripe_reconcile_next_at' => now(), 'stripe_reconcile_claim' => null]);

        $refund = Refund::create(
            ['payment_intent' => $order->stripe_payment_intent,
                'metadata' => ['order_id' => (string) $order->id]],
            ['idempotency_key' => 'order-refund-'.$order->id]
        );

        $this->applyCreatedRefund($refund, $order);

        return $refund;
    }

    /**
     * Rambursează plata Stripe pentru un retur primit.
     */
    public function refundReturn(ReturnRequest $return): Refund
    {
        app(StripeModePolicy::class)->livemode();
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

        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Stripe refund HTTP cannot run inside a DB transaction.');
        }

        // A lost POST reply must not leave recovery waiting for the weekly stable scan.
        Order::query()->whereKey($order->id)->update(['stripe_reconcile_next_at' => now(), 'stripe_reconcile_claim' => null]);

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
        $this->applyCreatedRefund($refund, $order, $return);

        return $refund;
    }

    public function read(string $resource, string $id): object
    {
        $this->assertReadOutsideTransaction();
        Stripe::setApiKey(config('services.stripe.secret'));
        $class = match ($resource) {
            'session' => Session::class,
            'payment_intent' => PaymentIntent::class,
            'charge' => Charge::class,
            'refund' => Refund::class,
            default => throw new \InvalidArgumentException('Unknown Stripe read resource.'),
        };

        return $class::retrieve($id, ['max_network_retries' => 0]);
    }

    public function page(string $resource, array $parameters): object
    {
        $this->assertReadOutsideTransaction();
        Stripe::setApiKey(config('services.stripe.secret'));

        return match ($resource) {
            'sessions' => Session::all($parameters, ['max_network_retries' => 0]),
            'refunds' => Refund::all($parameters, ['max_network_retries' => 0]),
            'events' => Event::all($parameters, ['max_network_retries' => 0]),
            default => throw new \InvalidArgumentException('Unknown Stripe list resource.'),
        };
    }

    public function allChargeRefunds(string $charge): array
    {
        $all = [];
        $after = null;
        do {
            $page = $this->page('refunds', array_filter(['charge' => $charge, 'limit' => 100, 'starting_after' => $after]));
            $data = $page->data ?? null;
            if (! is_array($data) || ! is_bool($page->has_more ?? null)
                || ($page->has_more && ($data === [] || end($data)->id === $after))) {
                throw new \RuntimeException('Invalid Stripe pagination.');
            }
            foreach ($data as $refund) {
                $all[$refund->id] = $refund;
                $after = $refund->id;
                if (count($all) > 1000) {
                    throw new \RuntimeException('Refund scan exceeds operational limit.');
                }
            }
        } while ($page->has_more);

        return array_values($all);
    }

    private function assertReadOutsideTransaction(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Stripe GET cannot run inside a DB transaction.');
        }
        app(StripeModePolicy::class)->livemode();
        $client = ApiRequestor::httpClient();
        if ($client instanceof CurlClient) {
            $client->setConnectTimeout((int) config('stripe_reconciliation.connect_timeout'));
            $client->setTimeout((int) config('stripe_reconciliation.request_timeout'));
        }
    }

    private function applyCreatedRefund(Refund $refund, Order $order, ?ReturnRequest $return = null): void
    {
        if (($refund->metadata->order_id ?? null) !== (string) $order->id
            || ($refund->metadata->return_request_id ?? null) !== ($return ? (string) $return->id : null)
            || ($refund->payment_intent ?? null) !== $order->stripe_payment_intent) {
            throw new \RuntimeException('Stripe refund response correlation requires manual review.');
        }
        $result = app(StripeStateApplier::class)->refund($refund);
        if ($result === 'manual_review') {
            throw new \RuntimeException('Stripe refund response requires manual review.');
        }
    }
}
