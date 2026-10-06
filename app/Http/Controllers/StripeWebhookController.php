<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\StripeModePolicy;
use App\Services\StripeRecoveryValidator;
use App\Services\StripeService;
use App\Services\StripeStateApplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
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

        $handled = str_starts_with($event->type, 'checkout.session.')
            || in_array($event->type, ['refund.updated', 'refund.failed', 'charge.refunded'], true);
        if ($handled) {
            try {
                app(StripeModePolicy::class)->livemode();
            } catch (\LogicException $e) {
                Log::error('Stripe mode configuration invalid.', ['event_id' => $event->id, 'event_type' => $event->type]);

                return response()->json(['message' => 'Stripe mode configuration unavailable.'], 503);
            }
        }
        $validator = app(StripeRecoveryValidator::class);
        if ($handled && (! $validator->context($event) || ! is_object($event->data->object ?? null)
            || ! $validator->context($event->data->object))) {
            Log::warning('Stripe webhook context rejected.', ['event_id' => $event->id, 'event_type' => $event->type]);

            return response()->json(['received' => true, 'ignored' => 'incompatible_context']);
        }

        if (str_starts_with($event->type, 'checkout.session.')) {
            if ($event->type === 'checkout.session.async_payment_failed') {
                try {
                    $result = app(StripeStateApplier::class)->asyncFailedWebhook($event);
                } catch (\Throwable $e) {
                    Log::warning('Stripe async failure validation unavailable.', ['event_id' => $event->id]);

                    return response()->json(['message' => 'Payment state validation unavailable.'], 503);
                }

                return response()->json(['received' => true, 'result' => $result]);
            }
            app(StripeStateApplier::class)->session($event->data->object, $event->type);
        }

        if (in_array($event->type, ['refund.updated', 'refund.failed'], true)) {
            app(StripeStateApplier::class)->refund($event->data->object, $event->type === 'refund.failed');
        }

        if ($event->type === 'charge.refunded') {
            $charge = $event->data->object;
            if ((int) ($charge->amount_refunded ?? 0) === (int) ($charge->amount ?? -1)) {
                $orders = Order::query()->where('stripe_payment_intent', $charge->payment_intent ?? '')->limit(2)->get();
                if ($orders->count() !== 1 || ($charge->currency ?? null) !== 'ron'
                    || ! is_int($charge->amount ?? null)
                    || $charge->amount !== (int) round((float) $orders->first()->total * 100)) {
                    return response()->json(['received' => true]);
                }
                try {
                    $refunds = app(StripeService::class)->allChargeRefunds($charge->id);
                    app(StripeStateApplier::class)->charge($charge, $refunds);
                } catch (\Throwable $e) {
                    report($e);

                    return response()->json(['message' => 'Refund could not be classified.'], 500);
                }
            }
        }

        return response()->json([
            'received' => true,
        ]);
    }
}
