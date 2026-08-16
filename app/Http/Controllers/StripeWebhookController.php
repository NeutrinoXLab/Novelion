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

        if ($event->type === 'checkout.session.completed') {

            $session = $event->data->object;

            $order = Order::where(
                'stripe_session_id',
                $session->id
            )->first();

            if ($order) {

                // Confirmă plata și actualizează stocul
                $this->orderService->markAsPaid($order);

                // Trimite e-mailul de confirmare
                Mail::to($order->email)
                    ->send(new OrderPaidMail($order));
            }

        }

        return response()->json([
            'received' => true,
        ]);
    }
}