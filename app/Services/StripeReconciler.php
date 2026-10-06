<?php

namespace App\Services;

use App\Models\CheckoutAttempt;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\StripeObject;

class StripeReconciler
{
    private int $reads = 0;

    private float $deadline = 0;

    private bool $validationStarted = false;

    private ?array $discoveryProof = null;

    public function __construct(private StripeService $stripe, private StripeStateApplier $applier) {}

    public function run(): array
    {
        app(StripeModePolicy::class)->livemode();
        $this->reads = max(4, (int) config('stripe_reconciliation.read_budget'));
        $this->deadline = microtime(true) + (int) config('stripe_reconciliation.runtime_seconds');
        $this->validationStarted = false;
        $counts = [];
        $orders = Order::query()->where('payment_method', 'stripe')
            ->where(fn ($q) => $q->whereNull('stripe_reconcile_next_at')->orWhere('stripe_reconcile_next_at', '<=', now()))
            ->orderBy('stripe_reconcile_next_at')->orderBy('id')
            ->limit((int) config('stripe_reconciliation.batch'))->get();
        foreach ($orders as $order) {
            if (! $this->available()) {
                break;
            }
            $lease = now()->addSeconds((int) config('stripe_reconciliation.lease_seconds'))->startOfSecond();
            $claimId = (string) Str::uuid();
            $claim = Order::query()->whereKey($order->id);
            if ($order->stripe_reconcile_next_at === null) {
                $claim->whereNull('stripe_reconcile_next_at');
            } else {
                $claim->where('stripe_reconcile_next_at', $order->stripe_reconcile_next_at);
            }
            $claim->where('stripe_reconcile_claim', $order->stripe_reconcile_claim);
            if ($claim->update(['stripe_reconcile_next_at' => $lease, 'stripe_reconcile_claim' => $claimId]) !== 1) {
                continue;
            }
            $cursor = $order->stripe_reconcile_cursor;
            $this->discoveryProof = null;
            // Persistence itself establishes cross-run provenance, including exceptions and crashes.
            if ($cursor !== null && ! $this->validCursor($cursor)) {
                $cursor = null;
                $invalidCursor = true;
            } else {
                $invalidCursor = false;
                if ($cursor !== null) {
                    $cursor['resumed'] = true;
                    if (in_array($cursor['phase'], ['refund_refresh', 'refund_confirm'], true)) {
                        $cursor['phase'] = 'refund_refresh';
                        $cursor['refresh_index'] = 0;
                    }
                }
            }
            try {
                $result = $invalidCursor ? 'manual_review' : $this->inspect($order, $cursor, $claimId);
                // Once current-run finalization starts, yielding would replay mandatory proof
                // with the same allowance forever. Enumeration may resume only before that point.
                if ($result === 'scan_incomplete' && $this->validationStarted) {
                    $cursor = null;
                    $result = 'manual_review';
                }
                $failures = 0;
            } catch (\Throwable $e) {
                // Store an error class/code, never Stripe payloads, secrets, URLs or customer data.
                $code = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : null;
                $result = in_array($code, [404, 429], true) ? 'api_'.$code : 'api_or_local_error';
                $failures = $order->stripe_reconcile_failures + 1;
            }
            if ($cursor !== null && ! $this->validCursor($cursor)) {
                $cursor = null;
                $result = 'manual_review';
            }
            if (in_array($result, ['claim_lost', 'lease_expired'], true)) {
                $counts[$result] = ($counts[$result] ?? 0) + 1;

                continue;
            }
            $delay = $this->delay($result, $failures, $order->id);
            // A crashed worker's lease expires; a stale worker cannot overwrite a newer claim.
            $saved = Order::query()->whereKey($order->id)->where('stripe_reconcile_claim', $claimId)
                ->where('stripe_reconcile_next_at', '>', now())->update([
                    'stripe_reconcile_claim' => null,
                    'stripe_reconcile_checked_at' => now(), 'stripe_reconcile_next_at' => now()->addSeconds($delay),
                    'stripe_reconcile_result' => $result, 'stripe_reconcile_failures' => $failures,
                    'stripe_reconcile_cursor' => $cursor === null ? null : json_encode($cursor, JSON_THROW_ON_ERROR),
                ]);
            if ($saved && (str_contains($result, 'manual_review')
                || $failures >= (int) config('stripe_reconciliation.alert_after_failures'))) {
                Log::warning('Stripe reconciliation needs manual attention.', [
                    'order_id' => $order->id, 'result' => $result, 'failures' => $failures,
                ]);
            }
            $outcome = $saved ? $result : 'completion_not_owned';
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
        }

        return $counts;
    }

    public static function snapshotContext(Order $order, CheckoutAttempt $attempt): string
    {
        return hash('sha256', json_encode([
            app(StripeModePolicy::class)->livemode(), hash('sha256', (string) config('services.stripe.secret')),
            config('stripe_reconciliation.page_size'),
            $order->id, $order->user_id, $order->payment_method, $order->stripe_session_id, $order->stripe_payment_intent,
            (string) $order->subtotal, (string) $order->shipping_cost, (string) $order->total,
            $attempt->id, $attempt->token, $attempt->user_id, $attempt->request_hash,
            $attempt->stripe_started_at?->timestamp, CheckoutAttempts::stripeFingerprint($attempt->stripe_parameters ?? []),
            $order->items()->orderBy('id')->get()->map(fn ($item) => [
                $item->id, $item->product_id, $item->product_name, (string) $item->price, $item->quantity,
            ])->all(),
        ], JSON_THROW_ON_ERROR));
    }

    private function inspect(Order $order, ?array &$cursor, string $claimId): string
    {
        $attempt = CheckoutAttempt::query()->where('order_id', $order->id)->first();
        if (! $attempt?->stripe_started_at || ! $attempt->stripe_parameters) {
            $cursor = null;

            return 'manual_review';
        }
        $context = self::snapshotContext($order, $attempt);
        if ($cursor !== null && (($cursor['context'] ?? null) !== $context || ($cursor['version'] ?? null) !== 3)) {
            // Never reuse a scan under a different identity/filter/window snapshot.
            $cursor = null;

            return 'manual_review';
        }
        if ($cursor !== null && ! $this->scanAllowed($cursor)) {
            return 'manual_review';
        }
        $sessionId = $order->stripe_session_id;
        if ($sessionId && $cursor !== null && (($cursor['session_id'] ?? null) !== $sessionId || $cursor['phase'] === 'discovery')) {
            $cursor = null;

            return 'manual_review';
        }
        if (! $sessionId) {
            $saved = $cursor;
            $proof = ($cursor['phase'] ?? null) === 'discovery' ? $cursor : ($cursor['discovery_proof'] ?? null);
            if ($saved !== null && $proof === null) {
                $cursor = null;

                return 'manual_review';
            }
            if ($proof !== null && ($proof['from'] !== $attempt->stripe_started_at->timestamp - 60
                || $proof['to'] !== min($proof['started_at'], $attempt->stripe_started_at->timestamp + 23 * 3600 + 60))) {
                $cursor = null;

                return 'manual_review';
            }
            if (($proof['complete'] ?? false) === true) {
                // A saved completed proof must be traversed again, not applied as a candidate certificate.
                $proof['complete'] = false;
                $proof['expected_digest'] = $proof['digest'];
                $proof['digest'] = '';
                $proof['after'] = null;
                $proof['matches'] = [];
            }
            if ($proof !== null) {
                $proof['resumed'] = true;
            }
            try {
                $result = $this->discover($order, $attempt, $proof, $context);
            } catch (\Throwable $e) {
                $cursor = $proof === null ? null : (($saved['phase'] ?? null) === 'discovery' || $saved === null ? $proof : array_replace($saved, ['discovery_proof' => $proof]));
                throw $e;
            }
            if ($result !== 'found') {
                $cursor = $proof === null ? null : (($saved['phase'] ?? null) === 'discovery' || $saved === null ? $proof : array_replace($saved, ['discovery_proof' => $proof]));

                return $result;
            }
            $sessionId = $proof['matches'][0];
            $this->discoveryProof = $proof;
            if (isset($saved['session_id']) && $saved['session_id'] !== $sessionId) {
                $cursor = null;

                return 'manual_review';
            }
            $cursor = ($saved !== null && $saved['phase'] !== 'discovery' ? $saved : $this->checkpoint($context, 'snapshot') + ['session_id' => $sessionId]);
            $cursor['discovery_proof'] = $proof;
            $this->beginValidation();
            try {
                if (! $this->verifyDiscovery($order, $attempt, $proof)) {
                    $cursor = null;

                    return 'manual_review';
                }
            } finally {
                if ($cursor !== null) {
                    $cursor['discovery_proof'] = $proof;
                    $this->discoveryProof = $proof;
                }
            }
        }
        if (! $this->available()) {
            return 'scan_incomplete';
        }
        $session = $this->get('session', $sessionId);
        $pi = null;
        $charge = null;
        $refunds = [];
        if (is_string($session->payment_intent ?? null)) {
            if (! $this->available()) {
                return 'scan_incomplete';
            }
            $pi = $this->get('payment_intent', $session->payment_intent);
            if ($pi->status === 'succeeded') {
                if (! is_string($pi->latest_charge ?? null)) {
                    $cursor = null;

                    return 'manual_review';
                }
                if (! $this->available()) {
                    return 'scan_incomplete';
                }
                $charge = $this->get('charge', $pi->latest_charge);
                if (! $this->refundPages($sessionId, $charge, $cursor, $refunds, $context)) {
                    return $cursor === null ? 'manual_review' : 'scan_incomplete';
                }
            }
        }
        $failure = null;
        if (($session->status ?? null) === 'complete' && ($session->payment_status ?? null) === 'unpaid'
            && ($pi->status ?? null) === 'requires_payment_method') {
            [$done, $failure] = ($cursor['phase'] ?? null) === 'async_proof'
                ? [true, StripeObject::constructFrom($cursor['proof'])]
                : $this->asyncFailure($session, $attempt, $cursor, $context);
            if (! $done) {
                return 'scan_incomplete';
            }
            if ($cursor === null && $failure === null) {
                return 'manual_review';
            }
            if ($failure && is_string($pi->latest_charge ?? null)) {
                $checkpoint = $this->checkpoint($context, 'async_proof');
                $checkpoint['started_at'] = $cursor['started_at'] ?? $checkpoint['started_at'];
                $checkpoint['pages'] = $cursor['pages'] ?? 0;
                $cursor = $checkpoint + ['session_id' => $sessionId,
                    'proof' => $this->failureData($failure)];
                // Leave enough budget for both the failed Charge and the PI recheck.
                if ($this->reads < 2 || ! $this->available()) {
                    return 'scan_incomplete';
                }
                $charge = $this->get('charge', $pi->latest_charge);
                $pi = $this->get('payment_intent', $session->payment_intent);
            }
        }
        // Phase replacement must not discard discovery provenance before an exception is checkpointed.
        if (! $order->stripe_session_id && isset($proof)) {
            $cursor['discovery_proof'] = $proof;
        }
        try {
            $result = $this->applier->recovered($order->id, $session, $pi, $charge, $refunds, $claimId, $context, $failure);
        } catch (\Throwable $e) {
            // The scan was consumed by a business transaction which rolled back. Retry through a fresh scan.
            $cursor = null;
            throw $e;
        }
        if ($result === 'payment_waiting' && ($session->status ?? null) === 'complete'
            && ($pi->status ?? null) === 'requires_payment_method'
            && $attempt->stripe_started_at->lte(now()->subSeconds((int) config('stripe_reconciliation.async_manual_after_seconds')))) {
            $result = 'async_failure_manual_review';
        }
        $cursor = null;

        return $result;
    }

    private function discover(Order $order, CheckoutAttempt $attempt, ?array &$cursor, string $context): string
    {
        if (($cursor['phase'] ?? null) !== 'discovery') {
            $cursor = $this->checkpoint($context, 'discovery');
            $cursor += ['after' => null, 'matches' => [], 'digest' => '',
                'from' => $attempt->stripe_started_at->timestamp - 60,
                'to' => min($cursor['started_at'], $attempt->stripe_started_at->timestamp + 23 * 3600 + 60)];
        }
        while (true) {
            do {
                if (! $this->scanAllowed($cursor)) {
                    return 'manual_review';
                }
                if (! $this->available()) {
                    $cursor['resumed'] = true;

                    return 'scan_incomplete';
                }
                $params = ['limit' => (int) config('stripe_reconciliation.page_size'),
                    'created' => ['gte' => $cursor['from'], 'lte' => $cursor['to']]];
                if ($order->stripe_payment_intent) {
                    $params['payment_intent'] = $order->stripe_payment_intent;
                }
                if ($cursor['after']) {
                    $params['starting_after'] = $cursor['after'];
                }
                $page = $this->list('sessions', $params);
                $this->validPage($page, $cursor['after']);
                $cursor['pages']++;
                foreach ($page->data as $session) {
                    $identity = [$session->id, $session->metadata->checkout_attempt ?? null, $session->metadata->order_id ?? null];
                    $cursor['digest'] = hash('sha256', $cursor['digest'].json_encode($identity, JSON_THROW_ON_ERROR));
                    if ($identity[1] === $attempt->token || $identity[2] === (string) $order->id) {
                        $cursor['matches'] = array_values(array_unique([...$cursor['matches'], $session->id]));
                    }
                    $cursor['after'] = $session->id;
                }
                if (count($cursor['matches']) > 1) {
                    $cursor = null;

                    return 'manual_review';
                }
            } while ($page->has_more);
            if (isset($cursor['expected_digest'])) {
                if (! hash_equals($cursor['expected_digest'], $cursor['digest'])) {
                    $cursor = null;

                    return 'manual_review';
                }
                break;
            }
            if (! ($cursor['resumed'] ?? false)) {
                break;
            }
            // A cross-run discovery needs a complete second pass over the identical frozen filter/window.
            $cursor['expected_digest'] = $cursor['digest'];
            $cursor['digest'] = '';
            $cursor['after'] = null;
            $cursor['matches'] = [];
        }
        $matches = $cursor['matches'];
        if ($matches === []) {
            $cursor = null;

            return 'session_not_found';
        }
        $cursor['complete'] = true;

        return 'found';
    }

    private function refundPages(string $sessionId, object $charge, ?array &$cursor, array &$refunds, string $context): bool
    {
        $chargeState = hash('sha256', json_encode([$charge->id, $charge->payment_intent ?? null,
            $charge->livemode ?? null, $charge->amount ?? null, $charge->amount_refunded ?? null], JSON_THROW_ON_ERROR));
        if (! in_array($cursor['phase'] ?? null, ['refunds', 'refund_refresh', 'refund_confirm'], true)
            || ($cursor['session_id'] ?? null) !== $sessionId || ($cursor['charge_state'] ?? null) !== $chargeState) {
            $cursor = $this->checkpoint($context, 'refunds') + ['session_id' => $sessionId,
                'charge_id' => $charge->id, 'charge_state' => $chargeState, 'after' => null, 'refunds' => [], 'refund_ids' => []];
        }
        if ($cursor['phase'] === 'refunds') {
            do {
                if (! $this->scanAllowed($cursor)) {
                    return false;
                }
                if (! $this->available()) {
                    $cursor['resumed'] = true;

                    return false;
                }
                $params = ['charge' => $charge->id, 'limit' => (int) config('stripe_reconciliation.page_size')];
                if ($cursor['after']) {
                    $params['starting_after'] = $cursor['after'];
                }
                $page = $this->list('refunds', $params);
                $this->validPage($page, $cursor['after']);
                $cursor['pages']++;
                $cursor['head'] ??= array_map(fn ($r) => $r->id, $page->data);
                foreach ($page->data as $refund) {
                    if (! isset($cursor['refunds'][$refund->id])) {
                        $cursor['refund_ids'][] = $refund->id;
                    }
                    $cursor['refunds'][$refund->id] = $this->refundData($refund);
                    $cursor['after'] = $refund->id;
                    if (count($cursor['refunds']) > 1000) {
                        $cursor = null;

                        return false;
                    }
                }
            } while ($page->has_more);
            if ($cursor['resumed'] ?? false) {
                $cursor['phase'] = 'refund_refresh';
                $cursor['refresh_ids'] = $cursor['refund_ids'];
                $cursor['refresh_index'] = 0;
            }
        }
        if (! $this->scanAllowed($cursor)) {
            return false;
        }
        if ($cursor['phase'] === 'refund_refresh') {
            $this->beginValidation();
            // All mutable Refund observations used by apply must be retrieved in THIS PHP run.
            $cursor['refresh_index'] = 0;
            if (count($cursor['refresh_ids']) + 1 > $this->reads) {
                $cursor = null;

                return false;
            }
            while ($cursor['refresh_index'] < count($cursor['refresh_ids'])) {
                if (! $this->available()) {
                    $cursor = null;

                    return false;
                }
                $id = $cursor['refresh_ids'][$cursor['refresh_index']];
                $refund = $this->get('refund', $id);
                if (($refund->id ?? null) !== $id) {
                    $cursor = null;

                    return false;
                }
                $cursor['refunds'][$id] = $this->refundData($refund);
                $cursor['refresh_index']++;
            }
            $cursor['phase'] = 'refund_confirm';
        }
        if ($cursor['phase'] === 'refund_confirm') {
            $ids = [];
            $after = null;
            do {
                if (! $this->available() || ! $this->scanAllowed($cursor)) {
                    $cursor = null;

                    return false;
                }
                $params = ['charge' => $charge->id, 'limit' => 100];
                if ($after !== null) {
                    $params['starting_after'] = $after;
                }
                $page = $this->list('refunds', $params);
                $this->validPage($page, $after);
                $cursor['pages']++;
                foreach ($page->data as $refund) {
                    $ids[] = $refund->id;
                    $after = $refund->id;
                    if (count($ids) > 1000 || ! isset($cursor['refunds'][$refund->id])
                        || CheckoutAttempts::stripeFingerprint($cursor['refunds'][$refund->id]) !== CheckoutAttempts::stripeFingerprint($this->refundData($refund))) {
                        $cursor = null;

                        return false;
                    }
                }
            } while ($page->has_more);
            if ($ids !== $cursor['refund_ids']) {
                $cursor = null;

                return false;
            }
        }
        $refunds = array_map(fn ($id) => StripeObject::constructFrom($cursor['refunds'][$id]), $cursor['refund_ids']);

        return true;
    }

    private function verifyDiscovery(Order $order, CheckoutAttempt $attempt, array &$proof): bool
    {
        // Incremental passes establish progress only. Final uniqueness is re-enumerated in this run.
        $after = null;
        $digest = '';
        $matches = [];
        do {
            if (! $this->available() || ! $this->scanAllowed($proof)) {
                return false;
            }
            $params = ['limit' => 100, 'created' => ['gte' => $proof['from'], 'lte' => $proof['to']]];
            if ($order->stripe_payment_intent) {
                $params['payment_intent'] = $order->stripe_payment_intent;
            }
            if ($after !== null) {
                $params['starting_after'] = $after;
            }
            $page = $this->list('sessions', $params);
            $this->validPage($page, $after);
            $proof['pages']++;
            foreach ($page->data as $session) {
                $identity = [$session->id, $session->metadata->checkout_attempt ?? null, $session->metadata->order_id ?? null];
                $digest = hash('sha256', $digest.json_encode($identity, JSON_THROW_ON_ERROR));
                if ($identity[1] === $attempt->token || $identity[2] === (string) $order->id) {
                    $matches[] = $session->id;
                }
                $after = $session->id;
            }
        } while ($page->has_more);

        return $matches === $proof['matches'] && hash_equals($proof['digest'], $digest);
    }

    private function asyncFailure(object $session, CheckoutAttempt $attempt, ?array &$cursor, string $context): array
    {
        if (($cursor['phase'] ?? null) !== 'async') {
            $cursor = $this->checkpoint($context, 'async') + ['session_id' => $session->id, 'after' => null,
                'from' => max($attempt->stripe_started_at->timestamp - 60, time() - 29 * 86400), 'to' => time()];
        }
        do {
            if (! $this->scanAllowed($cursor)) {
                return [true, null];
            }
            if (! $this->available()) {
                return [false, null];
            }
            $params = ['type' => 'checkout.session.async_payment_failed', 'limit' => (int) config('stripe_reconciliation.page_size'),
                'created' => ['gte' => $cursor['from'], 'lte' => $cursor['to']]];
            if ($cursor['after']) {
                $params['starting_after'] = $cursor['after'];
            }
            $page = $this->list('events', $params);
            $this->validPage($page, $cursor['after']);
            $cursor['pages']++;
            foreach ($page->data as $event) {
                if (($event->data->object->id ?? null) === $session->id) {
                    return [true, $event];
                }
                $cursor['after'] = $event->id;
            }
        } while ($page->has_more);

        return [true, null];
    }

    private function validPage(object $page, ?string $after): void
    {
        $data = $page->data ?? null;
        if (! is_array($data) || ! is_bool($page->has_more ?? null)
            || ($page->has_more && ($page->data === [] || end($data)->id === $after))) {
            throw new \RuntimeException('Invalid Stripe pagination.');
        }
    }

    private function checkpoint(string $context, string $phase): array
    {
        return ['version' => 3, 'context' => $context, 'phase' => $phase, 'started_at' => time(), 'pages' => 0]
            + ($this->discoveryProof === null ? [] : ['discovery_proof' => $this->discoveryProof]);
    }

    private function beginValidation(): void
    {
        if (! $this->validationStarted) {
            // One separate bounded finalization allowance per run; never extend deadline or lease.
            $this->reads += max(4, min(2014, (int) config('stripe_reconciliation.validation_read_budget', 1014)));
            $this->validationStarted = true;
        }
    }

    private function validCursor(mixed $cursor, int $depth = 0): bool
    {
        if ($depth > 1 || ! is_array($cursor) || ($cursor['version'] ?? null) !== 3
            || ! is_string($cursor['context'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $cursor['context'])
            || ! is_int($cursor['pages'] ?? null) || $cursor['pages'] < 0
            || $cursor['pages'] >= (int) config('stripe_reconciliation.scan_max_pages')
            || ! is_int($cursor['started_at'] ?? null) || $cursor['started_at'] > time()
            || $cursor['started_at'] < time() - (int) config('stripe_reconciliation.scan_max_seconds')) {
            return false;
        }
        $id = fn ($value, $prefix) => is_string($value) && preg_match('/^'.$prefix.'_[A-Za-z0-9_]{1,240}$/D', $value);
        $hash = fn ($value) => is_string($value) && ($value === '' || preg_match('/^[a-f0-9]{64}$/D', $value));
        if (array_key_exists('resumed', $cursor) && ! is_bool($cursor['resumed'])) {
            return false;
        }
        if (array_key_exists('discovery_proof', $cursor) && (! $this->validCursor($cursor['discovery_proof'], $depth + 1) || $cursor['discovery_proof']['phase'] !== 'discovery' || $cursor['discovery_proof']['context'] !== $cursor['context'])) {
            return false;
        }
        $phase = $cursor['phase'] ?? null;
        if (! in_array($phase, ['discovery', 'snapshot', 'refunds', 'refund_refresh', 'refund_confirm', 'async', 'async_proof'], true)) {
            return false;
        }
        $fields = ['version', 'context', 'phase', 'started_at', 'pages', 'resumed', 'discovery_proof'];
        $specific = match ($phase) {
            'discovery' => ['after', 'matches', 'digest', 'from', 'to', 'expected_digest', 'complete'],
            'snapshot' => ['session_id'],
            'refunds' => ['session_id', 'charge_id', 'charge_state', 'after', 'refunds', 'refund_ids', 'head'],
            'refund_refresh', 'refund_confirm' => ['session_id', 'charge_id', 'charge_state', 'after', 'refunds', 'refund_ids', 'head', 'refresh_ids', 'refresh_index'],
            'async' => ['session_id', 'after', 'from', 'to'],
            'async_proof' => ['session_id', 'proof'],
        };
        if (array_diff(array_keys($cursor), [...$fields, ...$specific]) !== []) {
            return false;
        }
        if ($phase !== 'discovery' && ! $id($cursor['session_id'] ?? null, 'cs')) {
            return false;
        }
        if (in_array($phase, ['discovery', 'refunds', 'refund_refresh', 'refund_confirm', 'async'], true)) {
            if (! array_key_exists('after', $cursor) || ($cursor['after'] !== null && ! $id($cursor['after'], $phase === 'discovery' ? 'cs' : ($phase === 'async' ? 'evt' : 're')))) {
                return false;
            }
        }
        if (in_array($phase, ['discovery', 'async'], true)) {
            if (! is_int($cursor['from'] ?? null) || ! is_int($cursor['to'] ?? null) || $cursor['from'] < 0 || $cursor['to'] < $cursor['from'] || $cursor['to'] > time()) {
                return false;
            }
        }
        if ($phase === 'discovery') {
            return is_array($cursor['matches'] ?? null) && array_is_list($cursor['matches']) && count($cursor['matches']) <= 1
                && ($cursor['matches'] === [] || $id($cursor['matches'][0], 'cs')) && $hash($cursor['digest'] ?? null)
                && (! isset($cursor['expected_digest']) || $hash($cursor['expected_digest']))
                && (! isset($cursor['complete']) || is_bool($cursor['complete']));
        }
        if (str_starts_with($phase, 'refund')) {
            if (! $id($cursor['charge_id'] ?? null, 'ch') || ! $hash($cursor['charge_state'] ?? null) || $cursor['charge_state'] === ''
                || ! is_array($cursor['refunds'] ?? null) || count($cursor['refunds']) > 1000) {
                return false;
            }
            if (! is_array($cursor['refund_ids'] ?? null) || ! array_is_list($cursor['refund_ids'])
                || count($cursor['refund_ids']) !== count($cursor['refunds'])
                || count(array_unique($cursor['refund_ids'], SORT_REGULAR)) !== count($cursor['refund_ids'])
                || array_filter($cursor['refund_ids'], fn ($value) => ! $id($value, 're'))
                || array_diff(array_keys($cursor['refunds']), $cursor['refund_ids']) !== []) {
                return false;
            }
            foreach ($cursor['refunds'] as $key => $refund) {
                if (! $id($key, 're') || ! is_array($refund) || ($refund['id'] ?? null) !== $key
                    || ($refund['object'] ?? null) !== 'refund' || ! is_bool($refund['livemode'] ?? null)
                    || ! $id($refund['charge'] ?? null, 'ch') || ! $id($refund['payment_intent'] ?? null, 'pi')
                    || ! is_int($refund['amount'] ?? null) || $refund['amount'] <= 0
                    || ! is_string($refund['currency'] ?? null) || ! preg_match('/^[a-z]{3}$/D', $refund['currency'])
                    || ! in_array($refund['status'] ?? null, ['pending', 'requires_action', 'succeeded', 'failed', 'canceled'], true)
                    || ! is_array($refund['metadata'] ?? null)) {
                    return false;
                }
            }
            if (isset($cursor['head']) && (! is_array($cursor['head']) || ! array_is_list($cursor['head']) || array_filter($cursor['head'], fn ($value) => ! $id($value, 're')))) {
                return false;
            }
            if ($phase !== 'refunds' && (! is_array($cursor['refresh_ids'] ?? null) || $cursor['refresh_ids'] !== $cursor['refund_ids']
                || ! is_int($cursor['refresh_index'] ?? null) || $cursor['refresh_index'] < 0 || $cursor['refresh_index'] > count($cursor['refresh_ids']) || ! isset($cursor['head']))) {
                return false;
            }
        }
        if ($phase === 'async_proof') {
            $proof = $cursor['proof'] ?? null;

            return is_array($proof) && $id($proof['id'] ?? null, 'evt') && ($proof['type'] ?? null) === 'checkout.session.async_payment_failed'
                && is_int($proof['created'] ?? null) && is_bool($proof['livemode'] ?? null) && is_array($proof['data']['object'] ?? null)
                && ($proof['data']['object']['id'] ?? null) === $cursor['session_id'];
        }

        return true;
    }

    private function scanAllowed(?array &$cursor): bool
    {
        if (($cursor['pages'] ?? 0) >= (int) config('stripe_reconciliation.scan_max_pages')
            || ($cursor['started_at'] ?? 0) < time() - (int) config('stripe_reconciliation.scan_max_seconds')) {
            $cursor = null;

            return false;
        }

        return true;
    }

    private function refundData(object $refund): array
    {
        $data = array_intersect_key($refund->toArray(), array_flip([
            'id', 'object', 'livemode', 'charge', 'payment_intent', 'currency', 'amount', 'status']));
        $data['metadata'] = array_intersect_key(($refund->metadata?->toArray() ?? []), array_flip(['order_id', 'return_request_id']));

        return $data;
    }

    private function failureData(object $event): array
    {
        $data = array_intersect_key($event->toArray(), array_flip(['id', 'object', 'livemode', 'type', 'created']));
        $object = $event->data->object;
        $data['data']['object'] = array_intersect_key($object->toArray(), array_flip([
            'id', 'object', 'livemode', 'mode', 'status', 'payment_status', 'currency', 'amount_total', 'payment_intent', 'client_reference_id']));
        $data['data']['object']['metadata'] = array_intersect_key(($object->metadata?->toArray() ?? []), array_flip([
            'order_id', 'user_id', 'checkout_attempt', 'request_hash', 'checkout_fingerprint']));

        return $data;
    }

    private function get(string $resource, string $id): object
    {
        $this->reads--;

        return $this->stripe->read($resource, $id);
    }

    private function list(string $resource, array $params): object
    {
        $this->reads--;

        return $this->stripe->page($resource, $params);
    }

    private function available(): bool
    {
        return $this->reads > 0 && microtime(true) < $this->deadline;
    }

    private function delay(string $result, int $failures, int $orderId): int
    {
        if ($failures > 0) {
            $steps = config('stripe_reconciliation.backoff_seconds');

            return $steps[min($failures - 1, count($steps) - 1)] + ($orderId % 31);
        }
        if (str_contains($result, 'manual_review')) {
            return (int) config('stripe_reconciliation.manual_seconds');
        }
        if (in_array($result, ['paid', 'refund_completed', 'return_refund_reconciled', 'expired_or_canceled', 'terminal_local_state'], true)) {
            return (int) config('stripe_reconciliation.stable_seconds');
        }

        return (int) config('stripe_reconciliation.pending_seconds');
    }
}
