<?php

namespace App\Http\Controllers;

use App\Mail\OrderPaidMail;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\SignatureVerificationException;
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
         * Plata finalizată prin Stripe Checkout.
         */
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $orderId = $session->metadata->order_id ?? null;

            $order = $orderId
                ? Order::find($orderId)
                : Order::where(
                    'stripe_session_id',
                    $session->id
                )->first();

            if ($order) {
                $order->update([
                    'stripe_session_id' => $session->id,
                    'stripe_payment_intent' => $session->payment_intent,
                ]);

                $wasAlreadyPaid =
                    $order->payment_status === 'paid';

                $this->orderService->markAsPaid($order);

                if (! $wasAlreadyPaid) {
                    Mail::to($order->email)
                        ->send(new OrderPaidMail($order));
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

            $orderId = $session->metadata->order_id ?? null;

            $order = $orderId
                ? Order::find($orderId)
                : Order::where(
                    'stripe_session_id',
                    $session->id
                )->first();

            if ($order) {
                if (! $order->stripe_session_id) {
                    $order->update([
                        'stripe_session_id' => $session->id,
                    ]);
                }

                if ($order->payment_status !== 'paid') {
                    $this->orderService->markAsFailed($order);
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

            $paymentIntent = $charge->payment_intent ?? null;

            if ($paymentIntent) {
                $order = Order::where(
                    'stripe_payment_intent',
                    $paymentIntent
                )->first();

                if ($order) {
                    \Stripe\Stripe::setApiKey(
                        config('services.stripe.secret')
                    );

                    /*
                     * Stripe ne permite să obținem direct toate
                     * refund-urile asociate acestui Charge.
                     */
                    try {
                        $refunds = \Stripe\Refund::all([
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
                            'message' =>
                                'Refund could not be classified.',
                        ], 500);
                    }

                    $returnRequestId = null;
                    $stripeRefundId = null;

                    foreach ($refunds->data as $refund) {
                        $metadataReturnRequestId =
                            $refund->metadata->return_request_id
                            ?? null;

                        if ($metadataReturnRequestId) {
                            $returnRequestId =
                                (int) $metadataReturnRequestId;

                            $stripeRefundId =
                                $refund->id ?? null;

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
                     * - NU restaurăm stocul aici;
                     * - NU anulăm comanda.
                     *
                     * Stocul a fost deja restaurat de
                     * EditReturnRequest.
                     */
                    if ($returnRequestId) {
                        $returnRequest = ReturnRequest::find(
                            $returnRequestId
                        );

                        if ($returnRequest) {
                            $returnRequest->update([
                                'status' => 'refunded',
                                'stripe_refund_id' =>
                                    $returnRequest->stripe_refund_id
                                    ?? $stripeRefundId,
                                'refunded_at' =>
                                    $returnRequest->refunded_at
                                    ?? now(),
                            ]);

                            /*
                             * Plata este rambursată, dar comanda
                             * rămâne "delivered".
                             */
                            $order->update([
                                'payment_status' => 'refunded',
                            ]);
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
                                . $returnRequestId
                            ));

                            return response()->json([
                                'message' =>
                                    'Return request could not be found.',
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
}