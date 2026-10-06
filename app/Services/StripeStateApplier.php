<?php

namespace App\Services;

use App\Models\CheckoutAttempt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;

class StripeStateApplier
{
    /** Always enter through attempt -> return (if any) -> order. No HTTP here. */
    public function locked(int $orderId, ?int $returnId, callable $apply): mixed
    {
        return DB::transaction(function () use ($orderId, $returnId, $apply) {
            $attempt = CheckoutAttempt::query()->where('order_id', $orderId)->lockForUpdate()->first();
            $return = $returnId === null ? null : ReturnRequest::query()
                ->whereKey($returnId)->where('order_id', $orderId)->lockForUpdate()->first();
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if (! $order || ($returnId !== null && ! $return)) {
                return 'manual_review';
            }

            $this->lockProducts($order);

            $result = $apply($order, $return, $attempt);
            if ($result === 'manual_review') {
                $order->update(['stripe_reconcile_result' => 'manual_review',
                    'stripe_reconcile_next_at' => now(), 'stripe_reconcile_claim' => null]);
            }

            return $result;
        }, 3);
    }

    public function refund(object $refund, bool $failedEvent = false): string
    {
        if (! app(StripeRecoveryValidator::class)->context($refund)) {
            return 'manual_review';
        }
        $orderId = $refund->metadata->order_id ?? null;
        $returnId = $refund->metadata->return_request_id ?? null;
        if (! ctype_digit((string) $orderId) || ($returnId !== null && ! ctype_digit((string) $returnId))) {
            return 'manual_review';
        }

        return $this->locked((int) $orderId, $returnId === null ? null : (int) $returnId,
            fn (Order $order, ?ReturnRequest $return): string => $this->applyRefund($order, $return, $refund, $failedEvent));
    }

    private function applyRefund(Order $order, ?ReturnRequest $return, object $refund, bool $failedEvent = false): string
    {
        $subject = $return ?? $order;
        $amount = (int) round((float) ($return ? $return->refund_amount : $order->total) * 100);
        if ($order->payment_method !== 'stripe'
            || ! is_string($refund->id ?? null) || $refund->id === ''
            || ! $order->stripe_payment_intent
            || $order->stripe_payment_intent !== ($refund->payment_intent ?? null)
            || ($subject->stripe_refund_id && $subject->stripe_refund_id !== $refund->id)
            || ($refund->currency ?? null) !== 'ron'
            || ! is_int($refund->amount ?? null) || $refund->amount !== $amount || $amount <= 0
            || ! in_array($refund->status ?? null, ['succeeded', 'pending', 'requires_action', 'failed', 'canceled'], true)) {
            return 'manual_review';
        }
        if (! $return && $order->returnRequests()->whereNotIn('status', ['rejected'])->exists()) {
            return 'manual_review';
        }
        if ($return && ! $this->safeReturn($order, $return)) {
            return 'manual_review';
        }
        // A stale observation must never undo a committed financial result.
        if ($subject->refund_status === 'completed') {
            $this->completeRefund($order, $return);

            return 'refund_completed';
        }
        if ($order->payment_status === 'refunded' && ! $return) {
            return 'refund_completed';
        }
        if ($subject->refund_status === 'failed' && $refund->status !== 'succeeded') {
            return 'refund_failed';
        }
        $completed = $refund->status === 'succeeded';
        $failed = $failedEvent || in_array($refund->status, ['failed', 'canceled'], true);
        $subject->update([
            'stripe_refund_id' => $refund->id,
            'refund_status' => $completed ? 'completed' : ($failed ? 'failed' : 'processing'),
        ]);
        if ($completed) {
            $this->completeRefund($order, $return);
        }

        return $completed ? 'refund_completed' : ($failed ? 'refund_failed' : 'refund_processing');
    }

    /** Apply a complete GET snapshot; all dependent rows are locked before effects. */
    public function recovered(int $orderId, object $session, ?object $pi, ?object $charge, array $refunds, string $claimId, string $expectedContext, ?object $asyncFailure = null): string
    {
        return DB::transaction(function () use ($orderId, $session, $pi, $charge, $refunds, $claimId, $asyncFailure, $expectedContext): string {
            $attempt = CheckoutAttempt::query()->where('order_id', $orderId)->lockForUpdate()->first();
            $returnIds = collect($refunds)->map(fn ($r) => $r->metadata->return_request_id ?? null)
                ->filter()->unique()->sort()->values();
            $returns = [];
            foreach ($returnIds as $id) {
                if (! ctype_digit((string) $id)) {
                    return 'manual_review';
                }
                $returns[(int) $id] = ReturnRequest::query()->whereKey((int) $id)
                    ->where('order_id', $orderId)->lockForUpdate()->first();
            }
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            // Fence every reconciliation effect using the current locked row, not a pre-HTTP check.
            if (! $order || $claimId === '' || $order->stripe_reconcile_claim !== $claimId) {
                return 'claim_lost';
            }
            if (! $order->stripe_reconcile_next_at || $order->stripe_reconcile_next_at->lte(now())) {
                return 'lease_expired';
            }
            if (! $attempt || ! hash_equals($expectedContext, StripeReconciler::snapshotContext($order, $attempt))) {
                return 'manual_review';
            }
            $validator = app(StripeRecoveryValidator::class);
            if (! $order || ! $validator->session($order, $attempt, $session, $pi)
                || (($pi?->status === 'succeeded' || $refunds !== [])
                    && (! $pi || ! $charge || ! $validator->charge($order, $pi, $charge, $refunds)))) {
                return 'manual_review';
            }
            $this->lockProducts($order);
            // Validate every refund before binding IDs, changing stock, or recording receipts.
            $returnRefundIds = [];
            foreach ($refunds as $refund) {
                $returnId = $refund->metadata->return_request_id ?? null;
                if ($returnId !== null) {
                    // Distinct IDs for one Return cannot be applied as a coherent batch.
                    // Reject before any binding/effect; list order must never pick a winner.
                    if (isset($returnRefundIds[(int) $returnId]) && $returnRefundIds[(int) $returnId] !== $refund->id) {
                        return 'manual_review';
                    }
                    $returnRefundIds[(int) $returnId] = $refund->id;
                    $return = $returns[(int) $returnId] ?? null;
                    if (! $return || ! $this->safeReturn($order, $return) || $return->refund_method !== 'stripe'
                        || strtolower((string) $return->refund_currency) !== 'ron'
                        || (string) $return->user_id !== (string) $order->user_id
                        || ! in_array($return->status, ['received', 'refunded'], true)
                        || ($refund->metadata->order_id ?? null) !== (string) $orderId
                        || ($return->stripe_refund_id && $return->stripe_refund_id !== $refund->id)
                        || $refund->amount !== (int) round((float) $return->refund_amount * 100)) {
                        return 'manual_review';
                    }
                } elseif (($refund->metadata->order_id ?? null) !== null
                    && ($refund->metadata->order_id ?? null) !== (string) $orderId) {
                    return 'manual_review';
                }
            }
            if ($asyncFailure !== null && ! ($session->payment_status === 'paid' && $pi?->status === 'succeeded')) {
                $decision = $pi ? app(StripeAsyncFailurePolicy::class)->decision($order, $attempt, $session, $pi, $charge, $asyncFailure) : 'manual_review';
                if ($decision !== 'async_failed') {
                    return $decision;
                }
            }
            if ($session->status === 'open' && isset($session->url)) {
                $url = parse_url($session->url);
                if (! is_array($url) || ($url['scheme'] ?? null) !== 'https'
                    || ! in_array($url['host'] ?? null, config('stripe_reconciliation.checkout_hosts'), true)
                    || isset($url['user']) || isset($url['pass'])) {
                    return 'manual_review';
                }
                $attempt->update(['stripe_session_url' => $session->url]);
            }
            $order->update(['stripe_session_id' => $session->id]);
            if ($pi) {
                $order->update(['stripe_payment_intent' => $pi->id]);
            }
            if ($refunds !== []) {
                // A PI remains succeeded after a refund: never enqueue an obsolete paid receipt.
                $unclassified = collect($refunds)->filter(fn ($r) => ! isset($r->metadata->return_request_id));
                if ($returnIds->isNotEmpty() && $unclassified->isNotEmpty()) {
                    return 'manual_review';
                }
                if ($returnIds->isNotEmpty()) {
                    foreach ($refunds as $refund) {
                        $result = $this->applyRefund($order, $returns[(int) $refund->metadata->return_request_id], $refund);
                        if ($result === 'manual_review') {
                            throw new \RuntimeException('Validated return refund changed during application.');
                        }
                    }
                    if ($charge->amount_refunded === $charge->amount) {
                        $order->update(['payment_status' => 'refunded', 'refund_status' => 'completed']);
                        app(TransactionalEmails::class)->refund($order);
                    }
                    if ($charge->amount_refunded < $charge->amount
                        && $order->payment_status !== 'refunded' && $order->status !== 'cancelled') {
                        app(OrderService::class)->markAsPaid($order);
                    }

                    return collect($refunds)->contains(fn ($r) => in_array($r->status, ['pending', 'requires_action'], true))
                        ? 'refund_processing' : 'return_refund_reconciled';
                }
                if ($charge->amount_refunded === $charge->amount
                    && ! $order->returnRequests()->whereNotIn('status', ['rejected'])->exists()) {
                    if ($order->stripe_refund_id && ! collect($refunds)->contains(fn ($r) => $r->id === $order->stripe_refund_id)) {
                        return 'manual_review';
                    }
                    $order->update(['refund_status' => 'completed',
                        'stripe_refund_id' => $order->stripe_refund_id ?: (count($refunds) === 1 ? $refunds[0]->id : null)]);
                    $this->completeRefund($order, null);

                    return 'refund_completed';
                }
                if (count($refunds) === 1 && ($refunds[0]->metadata->order_id ?? null) === (string) $orderId) {
                    return $this->applyRefund($order, null, $refunds[0]);
                }

                return 'manual_review';
            }
            if ($session->payment_status === 'paid' && $pi?->status === 'succeeded') {
                if ($order->payment_status === 'refunded') {
                    return 'refund_completed';
                }
                if ($order->status === 'cancelled') {
                    $order->update(['late_stripe_payment_at' => $order->late_stripe_payment_at ?? now()]);

                    return 'late_paid_manual_review';
                }
                if ($order->payment_status === 'paid') {
                    app(TransactionalEmails::class)->order($order, 'paid');
                } else {
                    app(OrderService::class)->markAsPaid($order);
                }

                return 'paid';
            }
            if (in_array($order->payment_status, ['paid', 'refunded'], true)) {
                return 'terminal_local_state';
            }
            if ($asyncFailure !== null) {
                app(OrderService::class)->markAsFailed($order);

                return 'async_failed';
            }
            if ($pi && in_array($pi->status, ['processing', 'requires_action', 'requires_capture', 'requires_confirmation'], true)) {
                return 'payment_waiting';
            }
            if (($session->status === 'expired' && (! $pi || $pi->status === 'canceled'))
                || ($session->status === 'complete' && $pi?->status === 'canceled')) {
                app(OrderService::class)->markAsFailed($order);

                return 'expired_or_canceled';
            }

            return 'payment_waiting';
        }, 3);
    }

    /** A signed full-charge observation; classification must include every refund page. */
    public function charge(object $charge, array $refunds): string
    {
        $orders = Order::query()->where('stripe_payment_intent', $charge->payment_intent ?? '')->limit(2)->get();
        if (! app(StripeRecoveryValidator::class)->context($charge)
            || $orders->count() !== 1 || ($charge->currency ?? null) !== 'ron'
            || ! is_int($charge->amount ?? null) || ! is_int($charge->amount_refunded ?? null)
            || $charge->amount_refunded !== $charge->amount) {
            return 'manual_review';
        }
        $refunded = 0;
        foreach ($refunds as $entry) {
            if (! is_string($entry->id ?? null) || $entry->id === ''
                || ($entry->charge ?? null) !== ($charge->id ?? null)
                || ($entry->payment_intent ?? null) !== $charge->payment_intent
                || ($entry->currency ?? null) !== 'ron' || ($entry->status ?? null) !== 'succeeded'
                || ! is_int($entry->amount ?? null) || $entry->amount <= 0
                || ! app(StripeRecoveryValidator::class)->context($entry)
                || (isset($entry->metadata->order_id) && $entry->metadata->order_id !== (string) $orders->first()->id)) {
                return 'manual_review';
            }
            $refunded += $entry->amount;
        }
        if ($refunded !== $charge->amount_refunded) {
            return 'manual_review';
        }
        $orderId = $orders->first()->id;
        $returns = collect($refunds)->filter(fn ($r) => isset($r->metadata->return_request_id));
        // Several return refunds require reconciliation individually, never arbitrary first-match stock restoration.
        if ($returns->count() > 1 || ($returns->isNotEmpty() && count($refunds) !== 1)) {
            return 'manual_review';
        }
        $refund = $returns->first();
        $returnId = $refund?->metadata?->return_request_id;
        if ($returnId !== null && ! ctype_digit((string) $returnId)) {
            return 'manual_review';
        }

        return $this->locked($orderId, $returnId === null ? null : (int) $returnId,
            function (Order $order, ?ReturnRequest $return) use ($charge, $refund): string {
                if ($order->payment_method !== 'stripe' || $order->stripe_payment_intent !== $charge->payment_intent
                    || $charge->amount !== (int) round((float) $order->total * 100)) {
                    return 'manual_review';
                }
                if ($return) {
                    if (! $this->safeReturn($order, $return)
                        || ($refund->metadata->order_id ?? null) !== (string) $order->id
                        || $refund->amount !== (int) round((float) $return->refund_amount * 100)
                        || ($return->stripe_refund_id && $return->stripe_refund_id !== $refund->id)) {
                        return 'manual_review';
                    }
                    $return->update(['stripe_refund_id' => $refund->id, 'refund_status' => 'completed']);
                    $this->completeRefund($order, $return);
                    $order->update(['payment_status' => 'refunded', 'refund_status' => 'completed']);
                    app(TransactionalEmails::class)->refund($order);
                } else {
                    if ($order->returnRequests()->whereNotIn('status', ['rejected'])->exists()) {
                        return 'manual_review';
                    }
                    $order->update(['refund_status' => 'completed']);
                    $this->completeRefund($order, null);
                }

                return 'refund_completed';
            });
    }

    /** Pre-acquire all stock locks by ID; legacy restore helpers only re-enter owned locks. */
    private function lockProducts(Order $order): void
    {
        $ids = $order->items()->pluck('product_id')->filter()->unique()->sort()->values();
        foreach ($ids as $id) {
            Product::query()->whereKey($id)->lockForUpdate()->first();
        }
    }

    /** The parent Return and Order are already locked. Never authorize the legacy all-items fallback. */
    private function safeReturn(Order $order, ReturnRequest $return): bool
    {
        if ($return->order_id !== $order->id || (string) $return->user_id !== (string) $order->user_id
            || ! in_array($return->status, ['received', 'refunded'], true)
            || ($return->refund_method !== null && $return->refund_method !== 'stripe')
            || strtolower((string) $return->refund_currency) !== 'ron'
            || ($order->stock_restored_at && ! $return->stock_restored_at)) {
            return false;
        }
        $items = ReturnItem::query()->where('return_request_id', $return->id)->orderBy('id')->lockForUpdate()->get();
        if ($items->isEmpty()) {
            return false;
        }
        if ($order->returnRequests()->whereKeyNot($return->id)->where('status', '!=', 'rejected')
            ->whereDoesntHave('items')->exists()) {
            // A legacy competing Return has an unknown stock claim; automatic eligibility is ambiguous.
            return false;
        }
        $sum = 0;
        foreach ($items as $item) {
            $ordered = OrderItem::query()->whereKey($item->order_item_id)->where('order_id', $order->id)
                ->lockForUpdate()->first();
            if (! $ordered || ! $ordered->product_id || ! Product::query()->whereKey($ordered->product_id)->exists()
                || ! is_numeric($item->quantity) || (int) $item->quantity != $item->quantity || (int) $item->quantity <= 0
                || (int) $item->quantity > $ordered->quantity
                || (int) round((float) $item->unit_price * 100) !== (int) round((float) $ordered->price * 100)
                || (int) round((float) $item->line_refund_amount * 100)
                    !== (int) round((float) $ordered->price * 100) * (int) $item->quantity) {
                return false;
            }
            // Active requests reserve eligible return quantities, including already refunded ones.
            // Current locking reads avoid a pre-lock MVCC snapshot. The OrderItem lock also
            // serializes creation of new Return positions; never take another Return lock after Order.
            $claimed = ReturnItem::query()->where('order_item_id', $ordered->id)
                ->whereHas('returnRequest', fn ($q) => $q->where('status', '!=', 'rejected'))
                ->orderBy('id')->lockForUpdate()->get();
            if ($claimed->contains(fn ($position) => (int) $position->quantity <= 0)
                || $claimed->sum('quantity') > $ordered->quantity) {
                return false;
            }
            $sum += (int) round((float) $item->line_refund_amount * 100);
        }
        $amount = (int) round((float) $return->refund_amount * 100);
        if ($amount === $sum) {
            return $sum > 0;
        }
        // Shipping can be allocated only once, to a withdrawal completing all returned quantities.
        $shipping = (int) round((float) $order->shipping_cost * 100);
        if ($return->type !== 'withdrawal' || $shipping <= 0 || $amount !== $sum + $shipping) {
            return false;
        }
        foreach ($order->items as $ordered) {
            $withdrawn = ReturnItem::query()->where('order_item_id', $ordered->id)
                ->whereHas('returnRequest', fn ($q) => $q->where('type', 'withdrawal')->where('status', '!=', 'rejected'))
                ->sum('quantity');
            if ($withdrawn !== $ordered->quantity && (int) $withdrawn !== (int) $ordered->quantity) {
                return false;
            }
        }
        foreach ($order->returnRequests()->whereKeyNot($return->id)->where('status', '!=', 'rejected')->with('items')->get() as $other) {
            if ((int) round((float) $other->refund_amount * 100)
                > (int) round((float) $other->items->sum('line_refund_amount') * 100)) {
                return false;
            }
        }

        return true;
    }

    private function completeRefund(Order $order, ?ReturnRequest $return): void
    {
        $subject = $return ?? $order;
        $subject->update(['refunded_at' => $subject->refunded_at ?? now()]);
        if ($return) {
            $return->update(['status' => 'refunded']);
            $return->restoreStock();
        } else {
            $order->update(['payment_status' => 'refunded', 'status' => 'cancelled']);
            app(OrderService::class)->restoreStock($order);
        }
        app(TransactionalEmails::class)->refund($subject);
    }

    public function session(object $session, string $eventType): void
    {
        // Destructive async failure must enter through an Event plus current authoritative GETs.
        if ($eventType === 'checkout.session.async_payment_failed') {
            return;
        }
        if (! app(StripeRecoveryValidator::class)->context($session)) {
            return;
        }
        // Preserve F2's durable recovered binding even when a later stock/outbox write rolls back.
        $bound = $this->findOrderForCheckoutSession($session, $eventType);
        if (! $bound) {
            return;
        }
        $this->locked($bound->id, null, function (Order $order) use ($session, $eventType): void {
            if ($order->stripe_session_id === ($session->id ?? null)) {
                $this->applySession($order, $session, $eventType);
            }
        });
    }

    private function applySession(Order $currentOrder, object $session, string $eventType): void
    {
        /*
         * Plata finalizată prin Stripe Checkout. Pentru metodele de plată
         * asincrone, confirmarea vine ulterior prin
         * checkout.session.async_payment_succeeded.
         */
        if (in_array($eventType, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ], true)) {

            $order = $currentOrder;

            if (($session->payment_status ?? null) === 'paid'
                && (! $order || ! $this->matchesPaidCheckout($order, $session))) {
                report(new \RuntimeException(
                    'Stripe paid Checkout session did not match a Novelion order: '.($session->id ?? 'unknown')
                ));
            }

            if ($order && $this->matchesPaidCheckout($order, $session)) {
                DB::transaction(function () use ($order, $session): ?Order {
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
                        app(TransactionalEmails::class)->order($order, 'paid');

                        return null;
                    }

                    $order->update(['stripe_payment_intent' => $session->payment_intent]);

                    return app(OrderService::class)->markAsPaid($order) ? $order : null;
                });

            }
        }

        /*
         * Checkout abandonat / expirat.
         *
         * Dacă plata nu a fost efectuată, eliberăm stocul.
         */
        if ($eventType === 'checkout.session.expired') {

            $order = $currentOrder;

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
                    app(OrderService::class)->markAsFailed($order);
                }
            }
        }

    }

    public function asyncFailedWebhook(object $event): string
    {
        $proof = $event->data->object ?? null;
        $id = $proof->metadata->order_id ?? null;
        if (! is_object($proof) || ! ctype_digit((string) $id)
            || ! is_string($proof->id ?? null) || ! is_string($proof->payment_intent ?? null)) {
            return 'manual_review';
        }
        $before = Order::query()->whereKey((int) $id)->where('payment_method', 'stripe')->first();
        if (! $before || ($before->stripe_session_id && $before->stripe_session_id !== $proof->id)
            || ($before->stripe_payment_intent && $before->stripe_payment_intent !== $proof->payment_intent)) {
            return 'manual_review';
        }
        $attempt = CheckoutAttempt::query()->where('order_id', $before->id)->first();
        $context = $this->asyncContext($before, $attempt);
        $stripe = app(StripeService::class);
        $session = $stripe->read('session', $proof->id);
        if (($session->id ?? null) !== $proof->id || ($session->payment_intent ?? null) !== $proof->payment_intent) {
            return 'manual_review';
        }
        $pi = $stripe->read('payment_intent', $proof->payment_intent);
        $charge = null;
        if (($pi->status ?? null) === 'requires_payment_method' && is_string($pi->latest_charge ?? null)) {
            $charge = $stripe->read('charge', $pi->latest_charge);
            // Recheck PI after the Charge GET: progress supersedes the failed snapshot.
            $pi = $stripe->read('payment_intent', $proof->payment_intent);
        }

        return $this->locked($before->id, null, function (Order $order, ?ReturnRequest $return, ?CheckoutAttempt $attempt) use ($event, $session, $pi, $charge, $context): string {
            if (in_array($order->payment_status, ['paid', 'refunded'], true)) {
                $result = 'terminal_local_state';
            } elseif (! hash_equals($context, $this->asyncContext($order, $attempt))) {
                $result = 'manual_review';
            } else {
                $result = app(StripeAsyncFailurePolicy::class)->decision($order, $attempt, $session, $pi, $charge, $event);
                if (in_array($result, ['async_failed', 'paid'], true)) {
                    $order->update(['stripe_session_id' => $session->id, 'stripe_payment_intent' => $pi->id]);
                    if ($result === 'async_failed') {
                        app(OrderService::class)->markAsFailed($order);
                    } else {
                        $this->applySession($order, $session, 'checkout.session.completed');
                        $result = $order->fresh()->status === 'cancelled' ? 'late_paid_manual_review' : 'paid';
                    }
                }
            }
            // A truthful new webhook observation invalidates any older worker snapshot.
            $order->update(['stripe_reconcile_result' => $result, 'stripe_reconcile_next_at' => now(), 'stripe_reconcile_claim' => null]);

            return $result;
        });
    }

    private function asyncContext(Order $order, ?CheckoutAttempt $attempt): string
    {
        return $attempt ? StripeReconciler::snapshotContext($order, $attempt) : hash('sha256', json_encode([
            app(StripeModePolicy::class)->livemode(), $order->id, $order->user_id, $order->payment_method,
            $order->stripe_session_id, $order->stripe_payment_intent, (string) $order->total,
            $order->items()->orderBy('id')->get()->map(fn ($item) => [$item->id, $item->product_id, (string) $item->price, $item->quantity])->all(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Găsește numai comenzile create pentru plata Stripe.
     */
    private function findOrderForCheckoutSession(object $session, string $eventType): ?Order
    {
        $orderId = $session->metadata->order_id ?? null;
        $sessionId = $session->id ?? null;

        if (! ctype_digit((string) $orderId) || ! is_string($sessionId)) {
            return null;
        }

        // Bound legacy sessions keep their existing strict ID lookup. Recovery is
        // available only for attempts whose correlation data committed before HTTP.
        $bound = Order::query()->whereKey((int) $orderId)->where('payment_method', 'stripe')
            ->where('stripe_session_id', $sessionId)->first();
        if ($bound) {
            return $bound;
        }
        $token = $session->metadata->checkout_attempt ?? null;
        if (! is_string($token) || $sessionId === '') {
            return null;
        }

        return DB::transaction(function () use ($token, $orderId, $sessionId, $session, $eventType): ?Order {
            // Same lock order as checkout. Wait for its save/rollback, then inspect
            // current values rather than a snapshot read made before it completed.
            $attempt = CheckoutAttempt::query()->where('token', $token)->lockForUpdate()->first();
            if (! $attempt || (string) $attempt->order_id !== (string) $orderId
                || ! $attempt->stripe_started_at || ! $attempt->stripe_parameters) {
                return null;
            }
            $order = Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            $parameters = $attempt->stripe_parameters;
            $metadata = $parameters['metadata'] ?? [];
            $fingerprint = $metadata['checkout_fingerprint'] ?? null;
            unset($parameters['metadata']['checkout_fingerprint']);
            if (! is_string($fingerprint)
                || ! hash_equals($fingerprint, CheckoutAttempts::stripeFingerprint($parameters))
                || ($metadata['checkout_attempt'] ?? null) !== $attempt->token
                || ($metadata['order_id'] ?? null) !== (string) $order->id
                || ($metadata['user_id'] ?? null) !== (string) $attempt->user_id
                || ($metadata['request_hash'] ?? null) !== $attempt->request_hash
                || $order->payment_method !== 'stripe'
                || (string) $order->user_id !== (string) $attempt->user_id
                || ($session->client_reference_id ?? null) !== $attempt->token
                || ($session->object ?? null) !== 'checkout.session'
                || ($session->mode ?? null) !== 'payment'
                || ($session->currency ?? null) !== 'ron'
                || ($session->status ?? null) !== ($eventType === 'checkout.session.expired' ? 'expired' : 'complete')
                || ! in_array($session->payment_status ?? null, ['paid', 'unpaid'], true)
                || (($session->status ?? null) === 'expired' && ($session->payment_status ?? null) !== 'unpaid')
                || ($order->stripe_session_id !== null && $order->stripe_session_id !== $sessionId)) {
                return null;
            }
            foreach ($metadata as $key => $value) {
                if (($session->metadata->{$key} ?? null) !== $value) {
                    return null;
                }
            }
            $expected = 0;
            foreach ($parameters['line_items'] as $item) {
                if ($item['price_data']['currency'] !== 'ron') {
                    return null;
                }
                $expected += $item['price_data']['unit_amount'] * $item['quantity'];
            }
            $orderCents = $order->items->sum(fn ($item): int => (int) round((float) $item->price * 100) * $item->quantity)
                + (int) round((float) $order->shipping_cost * 100);
            if (! is_int($session->amount_total ?? null) || $session->amount_total !== $expected
                || $expected !== $orderCents || $expected !== (int) round((float) $order->total * 100)
                || Order::query()->where('stripe_session_id', $sessionId)->whereKeyNot($order->id)->exists()) {
                return null;
            }
            if (($session->payment_status ?? null) === 'paid' && ! $this->matchesRecoveredPayment($order, $session)) {
                return null;
            }
            $order->update(['stripe_session_id' => $sessionId]);

            return $order;
        });
    }

    private function matchesRecoveredPayment(Order $order, object $session): bool
    {
        return is_string($session->payment_intent ?? null) && $session->payment_intent !== ''
            && ($order->stripe_payment_intent === null || $order->stripe_payment_intent === $session->payment_intent)
            && ! Order::query()->where('stripe_payment_intent', $session->payment_intent)->whereKeyNot($order->id)->exists();
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
