<?php

namespace App\Http\Controllers;

use App\Mail\OrderPaidMail;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $secret
            );
        } catch (SignatureVerificationException $e) {
            return response()->json([
                'message' => 'Invalid signature.',
            ], 400);
        } catch (\UnexpectedValueException $e) {
            return response()->json([
                'message' => 'Invalid payload.',
            ], 400);
        }

        /*
         * Plata finalizată prin Stripe Checkout. Pentru metodele de plată
         * asincrone, confirmarea vine ulterior prin
         * checkout.session.async_payment_succeeded.
         */
        if (in_array($event->type, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ], true)) {
            $session = $event->data->object;

            $order = $this->findOrderForCheckoutSession($session);

            if (($session->payment_status ?? null) === 'paid'
                && (! $order || ! $this->matchesPaidCheckout($order, $session))) {
                report(new \RuntimeException(
                    'Stripe paid Checkout session did not match a Novelion order: '.($session->id ?? 'unknown')
                ));
            }

            if ($order && $this->matchesPaidCheckout($order, $session)) {
                $paidOrder = DB::transaction(function () use ($order, $session): ?Order {
                    $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                    if (! $this->matchesPaidCheckout($order, $session)) {
                        return null;
                    }

                    if ($order->status === 'cancelled') {
                        if ($order->payment_status === 'refunded') {
                            return null;
                        }

                        if ($order->late_stripe_payment_at === null) {
                            $order->update([
                                'stripe_payment_intent' => $session->payment_intent,
                                'late_stripe_payment_at' => now(),
                            ]);
                            report(new \RuntimeException(
                                'Stripe confirmed payment for cancelled order '.$order->id
                            ));
                        }

                        return null;
                    }

                    if ($order->payment_status === 'paid') {
                        return null;
                    }

                    $order->update(['stripe_payment_intent' => $session->payment_intent]);

                    return $this->orderService->markAsPaid($order) ? $order : null;
                });

                if ($paidOrder) {
                    Mail::to($paidOrder->email)->send(new OrderPaidMail($paidOrder));
                }
            }
        }

        /*
         * Checkout abandonat / expirat.
         *
         * Dacă plata nu a fost efectuată, eliberăm stocul.
         */
        if ($event->type === 'checkout.session.expired') {
            $session = $event->data->object;

            $order = $this->findOrderForCheckoutSession($session);

            if ($order) {
                if (! $order->stripe_session_id) {
                    $order->update([
                        'stripe_session_id' => $session->id,
                    ]);
                }

                if (
                    $order->payment_status !== 'paid' &&
                    $order->status !== 'cancelled'
                ) {
                    $this->orderService->markAsFailed($order);
                }
            }
        }

        /*
         * O metodă de plată asincronă poate eșua după ce sesiunea Checkout a
         * fost deja finalizată. Eliberăm stocul rezervat, dar numai dacă plata
         * nu a fost confirmată între timp.
         */
        if ($event->type === 'checkout.session.async_payment_failed') {
            $session = $event->data->object;
            $order = $this->findOrderForCheckoutSession($session);

            if ($order) {
                $this->orderService->markAsFailed($order);
            }
        }

        if (in_array($event->type, ['refund.updated', 'refund.failed'], true)) {
            $refund = $event->data->object;
            $returnId = $refund->metadata->return_request_id ?? null;
            $orderId = $refund->metadata->order_id ?? null;
            if ($returnId && $orderId) {
                $return = ReturnRequest::query()->whereKey((int) $returnId)->where('order_id', (int) $orderId)->first();
                if ($return && $return->order->payment_method === 'stripe'
                    && $return->order->stripe_payment_intent === ($refund->payment_intent ?? null)
                    && (! $return->stripe_refund_id || $return->stripe_refund_id === ($refund->id ?? null))
                    && ($refund->currency ?? null) === 'ron'
                    && (int) ($refund->amount ?? -1) === (int) round((float) $return->refund_amount * 100)) {
                    if ($return->refund_status === 'completed') {
                        return response()->json(['received' => true]);
                    }
                    $failed = $event->type === 'refund.failed' || in_array($refund->status ?? null, ['failed', 'canceled'], true);
                    $completed = ($refund->status ?? null) === 'succeeded';
                    $return->update([
                        'stripe_refund_id' => $return->stripe_refund_id ?: $refund->id,
                        'refund_status' => $failed ? 'failed' : ($completed ? 'completed' : 'processing'),
                        'status' => $completed ? 'refunded' : $return->status,
                        'refunded_at' => $completed ? ($return->refunded_at ?? now()) : $return->refunded_at,
                    ]);
                    if ($completed) {
                        $return->restoreStock();
                    }
                }
            } elseif ($orderId) {
                $order = Order::query()->whereKey((int) $orderId)->where('payment_method', 'stripe')->first();
                if ($order && $order->stripe_payment_intent === ($refund->payment_intent ?? null)
                    && (! $order->stripe_refund_id || $order->stripe_refund_id === ($refund->id ?? null))
                    && ($refund->currency ?? null) === 'ron'
                    && (int) ($refund->amount ?? -1) === (int) round((float) $order->total * 100)) {
                    if ($order->refund_status === 'completed') {
                        return response()->json(['received' => true]);
                    }
                    $failed = $event->type === 'refund.failed' || in_array($refund->status ?? null, ['failed', 'canceled'], true);
                    $completed = ($refund->status ?? null) === 'succeeded';
                    $order->update([
                        'stripe_refund_id' => $order->stripe_refund_id ?: $refund->id,
                        'refund_status' => $failed ? 'failed' : ($completed ? 'completed' : 'processing'),
                        'refunded_at' => $completed ? now() : null,
                        'payment_status' => $completed ? 'refunded' : $order->payment_status,
                        'status' => $completed ? 'cancelled' : $order->status,
                    ]);
                    if ($completed) {
                        $this->orderService->restoreStock($order);
                    }
                }
            }
        }

        /*
         * Refund procesat prin Stripe.
         *
         * IMPORTANT:
         * Obiectul Charge primit de la Stripe nu conține în mod
         * necesar proprietatea "refunds".
         *
         * De aceea cerem explicit Refund-urile asociate Charge-ului
         * și verificăm metadata pentru return_request_id.
         */
        if ($event->type === 'charge.refunded') {
            $charge = $event->data->object;

            /*
             * charge.refunded este emis și pentru refund-uri parțiale. Nu
             * anulăm o comandă și nu restaurăm întregul stoc până când Charge
             * nu este rambursat integral.
             */
            if (
                (int) ($charge->amount_refunded ?? 0) <
                (int) ($charge->amount ?? 0)
            ) {
                return response()->json([
                    'received' => true,
                ]);
            }

            $paymentIntent = $charge->payment_intent ?? null;

            if ($paymentIntent) {
                $matchingOrders = Order::query()
                    ->where('stripe_payment_intent', $paymentIntent)
                    ->limit(2)
                    ->get();

                if ($matchingOrders->count() > 1) {
                    report(new \RuntimeException(
                        'Stripe PaymentIntent belongs to multiple Novelion orders: '.$paymentIntent
                    ));

                    return response()->json(['received' => true]);
                }

                $order = $matchingOrders->first();

                if ($order) {
                    if (
                        ($charge->currency ?? null) !== 'ron'
                        || ! is_int($charge->amount ?? null)
                        || $charge->amount !== (int) round((float) $order->total * 100)
                    ) {
                        report(new \RuntimeException(
                            'Stripe refund amount or currency mismatch for order '.$order->id
                        ));

                        return response()->json(['received' => true]);
                    }

                    Stripe::setApiKey(
                        config('services.stripe.secret')
                    );

                    /*
                     * Stripe ne permite să obținem direct toate
                     * refund-urile asociate acestui Charge.
                     */
                    try {
                        $refunds = Refund::all([
                            'charge' => $charge->id,
                        ]);
                    } catch (\Throwable $e) {
                        /*
                         * Dacă nu putem identifica refund-ul,
                         * NU modificăm comanda și NU restaurăm stocul.
                         *
                         * Returnăm 500 pentru ca Stripe să poată
                         * retrimite webhook-ul.
                         */
                        report($e);

                        return response()->json([
                            'message' => 'Refund could not be classified.',
                        ], 500);
                    }

                    $returnRequestId = null;
                    $stripeRefundId = null;
                    $metadataOrderId = null;

                    foreach ($refunds->data as $refund) {
                        $metadataReturnRequestId =
                            $refund->metadata->return_request_id
                            ?? null;

                        if ($metadataReturnRequestId) {
                            $returnRequestId =
                                (int) $metadataReturnRequestId;

                            $stripeRefundId =
                                $refund->id ?? null;
                            $metadataOrderId = $refund->metadata->order_id ?? null;

                            break;
                        }
                    }

                    /*
                     * ==========================================
                     * CAZ 1: Refund generat de un retur Novelion
                     * ==========================================
                     *
                     * Refund-ul a fost deja însoțit de metadata:
                     *
                     * return_request_id
                     * order_id
                     *
                     * În acest caz:
                     *
                     * - actualizăm returul;
                     * - restaurăm stocul idempotent;
                     * - NU anulăm comanda.
                     *
                     * Webhook-ul poate sosi înaintea acțiunii Filament.
                     */
                    if ($returnRequestId) {
                        $returnRequest = ReturnRequest::find(
                            $returnRequestId
                        );

                        if ($returnRequest) {
                            if (
                                $returnRequest->order_id !== $order->id
                                || $metadataOrderId !== (string) $order->id
                            ) {
                                report(new \RuntimeException(
                                    'Stripe return refund metadata mismatch for order '.$order->id
                                ));

                                return response()->json(['received' => true]);
                            }

                            $returnRequest->update([
                                'status' => 'refunded',
                                'refund_status' => 'completed',
                                'stripe_refund_id' => $returnRequest->stripe_refund_id
                                    ?? $stripeRefundId,
                                'refunded_at' => $returnRequest->refunded_at
                                    ?? now(),
                            ]);

                            /*
                             * Plata este rambursată, dar comanda
                             * rămâne "delivered".
                             */
                            $order->update([
                                'payment_status' => 'refunded',
                                'refund_status' => 'completed',
                            ]);

                            $returnRequest->restoreStock();
                        } else {
                            /*
                             * Refund-ul Stripe indică un retur care
                             * nu mai există în baza de date.
                             *
                             * NU marcăm webhook-ul ca procesat cu succes.
                             * Returnăm 500 pentru ca Stripe să poată
                             * retrimite webhook-ul.
                             */
                            report(new \RuntimeException(
                                'Stripe refund references a missing return request: '
                                .$returnRequestId
                            ));

                            return response()->json([
                                'message' => 'Return request could not be found.',
                            ], 500);
                        }

                        /*
                         * ==========================================
                         * CAZ 2: Refund normal al unei comenzi
                         * ==========================================
                         *
                         * Nu există metadata return_request_id.
                         *
                         * Acesta este un refund administrativ/normal,
                         * deci comanda este anulată și stocul este
                         * restaurat prin OrderService.
                         */
                    } else {
                        $order->update([
                            'payment_status' => 'refunded',
                            'refund_status' => 'completed',
                            'status' => 'cancelled',
                        ]);

                        $this->orderService->restoreStock($order);
                    }
                }
            }
        }

        return response()->json([
            'received' => true,
        ]);
    }

    /**
     * Găsește numai comenzile create pentru plata Stripe.
     */
    private function findOrderForCheckoutSession(object $session): ?Order
    {
        $orderId = $session->metadata->order_id ?? null;
        $sessionId = $session->id ?? null;

        if (! ctype_digit((string) $orderId) || ! is_string($sessionId)) {
            return null;
        }

        return Order::query()
            ->whereKey((int) $orderId)
            ->where('payment_method', 'stripe')
            ->where('stripe_session_id', $sessionId)
            ->first();
    }

    private function matchesPaidCheckout(Order $order, object $session): bool
    {
        if (
            $order->stripe_session_id !== ($session->id ?? null)
            || ($session->payment_status ?? null) !== 'paid'
            || ($session->currency ?? null) !== 'ron'
            || ! is_string($session->payment_intent ?? null)
            || $session->payment_intent === ''
            || ($order->stripe_payment_intent !== null
                && $order->stripe_payment_intent !== $session->payment_intent)
            || Order::query()
                ->where('stripe_payment_intent', $session->payment_intent)
                ->whereKeyNot($order->id)
                ->exists()
        ) {
            return false;
        }

        $expectedCents = $order->items->sum(
            fn ($item): int => (int) round((float) $item->price * 100) * $item->quantity
        ) + (int) round((float) $order->shipping_cost * 100);

        return $expectedCents === (int) round((float) $order->total * 100)
            && is_int($session->amount_total ?? null)
            && $session->amount_total === $expectedCents;
    }
}
