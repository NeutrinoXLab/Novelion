```php
<?php

namespace App\Http\Controllers;

use App\Mail\OrderPaidMail;
use App\Models\Order;
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
        |--------------------------------------------------------------------------
        | PLATĂ CONFIRMATĂ
        |--------------------------------------------------------------------------
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

                /*
                 * Dacă plata este deja confirmată,
                 * nu mai trimitem încă o dată emailul.
                 */
                $wasAlreadyPaid = $order->payment_status === 'paid';

                $this->orderService->markAsPaid($order);

                if (! $wasAlreadyPaid) {
                    Mail::to($order->email)
                        ->send(new OrderPaidMail($order));
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PLATĂ RAMBURSATĂ
        |--------------------------------------------------------------------------
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

                    $order->update([
                        'payment_status' => 'refunded',
                        'status' => 'cancelled',
                    ]);
                }
            }
        }

        return response()->json([
            'received' => true,
        ]);
    }
}
```
