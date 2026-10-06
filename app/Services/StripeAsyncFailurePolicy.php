<?php

namespace App\Services;

use App\Models\CheckoutAttempt;
use App\Models\Order;

class StripeAsyncFailurePolicy
{
    /** Requires current GETs, never authorizes cancellation from an event timestamp alone. */
    public function decision(Order $order, ?CheckoutAttempt $attempt, object $session, object $pi, ?object $charge, object $event): string
    {
        $validator = app(StripeRecoveryValidator::class);
        $proof = $event->data->object ?? null;
        $total = (int) round((float) $order->total * 100);
        if (($event->object ?? null) !== 'event' || ! is_string($event->id ?? null) || $event->id === ''
            || ($event->type ?? null) !== 'checkout.session.async_payment_failed'
            || ! $validator->context($event) || ! is_int($event->created ?? null)
            || $event->created <= 0 || $event->created > time() + 60
            || ($attempt?->stripe_started_at && $event->created < $attempt->stripe_started_at->timestamp - 60)
            || ! is_object($proof) || ! $validator->context($proof)
            || ($proof->object ?? null) !== 'checkout.session' || ($proof->mode ?? null) !== 'payment'
            || ($proof->status ?? null) !== 'complete' || ($proof->payment_status ?? null) !== 'unpaid'
            || ($proof->id ?? null) !== ($session->id ?? null)
            || ($proof->payment_intent ?? null) !== ($pi->id ?? null)
            || ($proof->metadata->order_id ?? null) !== (string) $order->id
            || ($proof->currency ?? null) !== 'ron' || ($proof->amount_total ?? null) !== $total
            || ! $validator->context($session) || ! $validator->context($pi)
            || ($session->object ?? null) !== 'checkout.session' || ($session->mode ?? null) !== 'payment'
            || ($session->status ?? null) !== 'complete'
            || ! in_array($session->payment_status ?? null, ['paid', 'unpaid'], true)
            || ($session->metadata->order_id ?? null) !== (string) $order->id
            || ($session->currency ?? null) !== 'ron' || ($session->amount_total ?? null) !== $total
            || ($session->payment_intent ?? null) !== ($pi->id ?? null)
            || ($order->stripe_session_id && $order->stripe_session_id !== $session->id)
            || ($order->stripe_payment_intent && $order->stripe_payment_intent !== $pi->id)
            || ($pi->object ?? null) !== 'payment_intent' || ($pi->currency ?? null) !== 'ron'
            || ($pi->amount ?? null) !== $total
            || Order::query()->where('stripe_session_id', $session->id)->whereKeyNot($order->id)->exists()
            || Order::query()->where('stripe_payment_intent', $pi->id)->whereKeyNot($order->id)->exists()) {
            return 'manual_review';
        }
        if ($attempt && (! $validator->session($order, $attempt, $session, $pi)
            || ! $validator->session($order, $attempt, $proof, $pi))) {
            return 'manual_review';
        }
        if (in_array($order->payment_status, ['paid', 'refunded'], true)) {
            return 'terminal_local_state';
        }
        if (in_array($pi->status ?? null, ['processing', 'requires_confirmation', 'requires_action', 'requires_capture'], true)) {
            return 'payment_waiting';
        }
        if (($pi->status ?? null) === 'succeeded') {
            return $session->payment_status === 'paid' && ($pi->amount_received ?? null) === $total ? 'paid' : 'payment_waiting';
        }
        // requires_payment_method is retryable. Only a correlated current failed Charge is definitive.
        if (($pi->status ?? null) !== 'requires_payment_method' || $session->payment_status !== 'unpaid'
            || ! $charge || ! $validator->context($charge) || ($charge->object ?? null) !== 'charge'
            || ! is_string($charge->id ?? null) || $charge->id === ''
            || ($pi->latest_charge ?? null) !== $charge->id
            || ($pi->last_payment_error->charge ?? null) !== $charge->id
            || ($charge->payment_intent ?? null) !== $pi->id || ($charge->status ?? null) !== 'failed'
            || ($charge->paid ?? null) !== false || ($charge->currency ?? null) !== 'ron'
            || ($charge->amount ?? null) !== $total || ($pi->amount_received ?? null) !== 0
            || ! is_int($charge->created ?? null) || $charge->created <= 0
            // Equality cannot establish order between distinct events/attempts in the same second.
            || $event->created <= $charge->created) {
            return 'manual_review';
        }

        return 'async_failed';
    }
}
