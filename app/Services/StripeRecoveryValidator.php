<?php

namespace App\Services;

use App\Models\CheckoutAttempt;
use App\Models\Order;

class StripeRecoveryValidator
{
    public function session(Order $order, ?CheckoutAttempt $attempt, object $session, ?object $pi): bool
    {
        if (! $attempt || ! $attempt->stripe_started_at || ! $attempt->stripe_parameters
            || ! $attempt->request_hash || $order->payment_method !== 'stripe'
            || $order->user_id === null || (string) $order->user_id !== (string) $attempt->user_id
            || ! $this->context($session) || ($session->object ?? null) !== 'checkout.session'
            || ! is_string($session->id ?? null) || $session->id === ''
            || ($order->stripe_session_id && $order->stripe_session_id !== $session->id)
            || ($session->client_reference_id ?? null) !== $attempt->token
            || ($session->mode ?? null) !== 'payment' || ($session->currency ?? null) !== 'ron'
            || ! in_array($session->status ?? null, ['open', 'complete', 'expired'], true)
            || ! in_array($session->payment_status ?? null, ['unpaid', 'paid'], true)
            || (($session->status !== 'complete') && $session->payment_status !== 'unpaid')
            || Order::query()->where('stripe_session_id', $session->id)->whereKeyNot($order->id)->exists()) {
            return false;
        }
        $parameters = $attempt->stripe_parameters;
        $metadata = $parameters['metadata'] ?? [];
        $fingerprint = $metadata['checkout_fingerprint'] ?? null;
        unset($parameters['metadata']['checkout_fingerprint']);
        if (! is_string($fingerprint) || ! hash_equals($fingerprint, CheckoutAttempts::stripeFingerprint($parameters))
            || ($metadata['checkout_attempt'] ?? null) !== $attempt->token
            || ($metadata['order_id'] ?? null) !== (string) $order->id
            || ($metadata['user_id'] ?? null) !== (string) $attempt->user_id
            || ($metadata['request_hash'] ?? null) !== $attempt->request_hash) {
            return false;
        }
        foreach ($metadata as $key => $value) {
            if (($session->metadata->{$key} ?? null) !== $value) {
                return false;
            }
        }
        // Compare the actual immutable local items/shipping, not only their sum.
        $expectedLines = [];
        foreach ($order->items as $item) {
            $expectedLines[] = ['name' => $item->product_name,
                'amount' => (int) round((float) $item->price * 100), 'quantity' => $item->quantity];
        }
        $subtotal = array_sum(array_map(fn ($line) => $line['amount'] * $line['quantity'], $expectedLines));
        if ($subtotal !== (int) round((float) $order->subtotal * 100)) {
            return false;
        }
        if ((float) $order->shipping_cost > 0) {
            $expectedLines[] = ['name' => 'Transport', 'amount' => (int) round((float) $order->shipping_cost * 100), 'quantity' => 1];
        }
        $lines = [];
        $total = 0;
        foreach ($parameters['line_items'] ?? [] as $line) {
            $price = $line['price_data'] ?? [];
            if (($price['currency'] ?? null) !== 'ron' || ! is_int($price['unit_amount'] ?? null)
                || ! is_int($line['quantity'] ?? null) || $line['quantity'] < 1) {
                return false;
            }
            $lines[] = ['name' => $price['product_data']['name'] ?? null,
                'amount' => $price['unit_amount'], 'quantity' => $line['quantity']];
            $total += $price['unit_amount'] * $line['quantity'];
        }
        if ($lines !== $expectedLines || $total !== (int) round((float) $order->total * 100)
            || ! is_int($session->amount_total ?? null) || $session->amount_total !== $total) {
            return false;
        }
        $piId = $session->payment_intent ?? null;
        if ($piId === null) {
            return $pi === null && $session->payment_status === 'unpaid' && $order->stripe_payment_intent === null;
        }
        if (! is_string($piId) || ! $pi || ! $this->context($pi)
            || ($pi->object ?? null) !== 'payment_intent' || ($pi->id ?? null) !== $piId
            || ($pi->currency ?? null) !== 'ron' || ! is_int($pi->amount ?? null) || $pi->amount !== $total
            || ($order->stripe_payment_intent && $order->stripe_payment_intent !== $piId)
            || Order::query()->where('stripe_payment_intent', $piId)->whereKeyNot($order->id)->exists()
            || ! in_array($pi->status ?? null, ['requires_payment_method', 'requires_confirmation', 'requires_action',
                'processing', 'requires_capture', 'canceled', 'succeeded'], true)) {
            return false;
        }

        return $session->payment_status !== 'paid'
            || ($pi->status === 'succeeded' && is_int($pi->amount_received ?? null) && $pi->amount_received === $total);
    }

    public function context(object $object): bool
    {
        $expected = app(StripeModePolicy::class)->livemode();

        return is_bool($expected) && is_bool($object->livemode ?? null)
            && $object->livemode === $expected;
    }

    public function charge(Order $order, object $pi, object $charge, array $refunds): bool
    {
        if (! $this->context($charge) || ($charge->object ?? null) !== 'charge'
            || ! is_string($charge->id ?? null) || ($pi->latest_charge ?? null) !== $charge->id
            || ($charge->payment_intent ?? null) !== $pi->id || ($charge->currency ?? null) !== 'ron'
            || ! is_int($charge->amount ?? null) || $charge->amount !== (int) round((float) $order->total * 100)
            || ! is_int($charge->amount_refunded ?? null) || $charge->amount_refunded < 0
            || $charge->amount_refunded > $charge->amount || ($charge->paid ?? null) !== true) {
            return false;
        }
        $sum = 0;
        foreach ($refunds as $refund) {
            if (! $this->context($refund) || ($refund->object ?? null) !== 'refund'
                || ! is_string($refund->id ?? null) || $refund->id === ''
                || ($refund->charge ?? null) !== $charge->id || ($refund->payment_intent ?? null) !== $pi->id
                || ($refund->currency ?? null) !== 'ron' || ! is_int($refund->amount ?? null) || $refund->amount <= 0
                || ! in_array($refund->status ?? null, ['succeeded', 'pending', 'requires_action', 'failed', 'canceled'], true)) {
                return false;
            }
            if ($refund->status === 'succeeded') {
                $sum += $refund->amount;
            }
        }

        return $sum === $charge->amount_refunded;
    }
}
