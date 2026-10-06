<?php

namespace App\Services;

use App\Models\CheckoutAttempt;
use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Refund;

class AdminOrderLifecycle
{
    public function transition(Order $order, string $target): Order
    {
        return DB::transaction(function () use ($order, $target): Order {
            // Same ordering as StripeStateApplier; no external HTTP in this transaction.
            CheckoutAttempt::query()->where('order_id', $order->id)->lockForUpdate()->first();
            // Bank refund locks the return first. Serialize collection with its result.
            $returns = $target === 'cash_paid'
                ? $order->returnRequests()->orderBy('id')->lockForUpdate()->get()
                : collect();
            $current = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($target === 'cancelled') {
                try {
                    app(OrderService::class)->cancel($current);
                } catch (QueryException $exception) {
                    throw $exception;
                } catch (\RuntimeException $exception) {
                    $this->reject($exception->getMessage());
                }

                return $current->fresh();
            }

            // Only record the actual delivery of an already dispatched, refunded parcel.
            // This exception never dispatches goods or changes financial/stock state.
            if ($target === 'delivered' && $current->payment_method === 'stripe'
                && $current->payment_status === 'refunded' && $current->refund_status === 'completed'
                && $current->stock_restored_at === null && $current->hasDispatchHistory()
                && $current->shipped_at !== null
                && ($current->status === 'shipped'
                    || ($current->status === 'delivered' && $current->delivered_at !== null))) {
                if ($current->status === 'shipped') {
                    $current->update(['status' => 'delivered', 'delivered_at' => $current->delivered_at ?? now()]);
                }

                return $current;
            }

            if ($current->stock_restored_at !== null || $current->refund_status !== null
                || $current->stripe_refund_id !== null
                || ! in_array($current->status, ['new', 'pending', 'processing', 'shipped', 'delivered'], true)
                || ! in_array($current->payment_status, ['pending', 'paid'], true)
                || ! in_array($current->payment_method, ['cash', 'stripe'], true)) {
                $this->reject('Starea curentă nu permite această operație. Verifică plata, returul și stocul.');
            }
            if ($target === 'cash_paid') {
                if ($returns->contains(fn ($return) => $return->status === 'refunded'
                    || $return->stock_restored_at !== null
                    || in_array($return->refund_status, ['initiated', 'processing', 'completed'], true))) {
                    $this->reject('Verifică rambursarea înainte de confirmarea încasării.');
                }
                if ($current->payment_method !== 'cash') {
                    $this->reject('Plata Stripe se confirmă numai prin procesator.');
                }
                if ($current->payment_status !== 'paid') {
                    $current->update(['payment_status' => 'paid']);
                    app(TransactionalEmails::class)->order($current, 'paid');
                }

                return $current;
            }

            if ($current->hasDispatchHistory() && in_array($current->status, ['new', 'pending', 'processing'], true)) {
                $this->reject('Istoricul expedierii nu corespunde stării curente. Verifică această comandă înainte de procesare.');
            }
            if ($current->returnRequests()->whereNotIn('status', ['rejected'])->exists()) {
                $this->reject('Comanda are un retur activ sau rambursat.');
            }

            if ($current->payment_method === 'stripe' && $current->payment_status !== 'paid') {
                $this->reject('Plata Stripe trebuie confirmată înainte de procesare.');
            }

            $next = ['new' => 'processing', 'pending' => 'processing', 'processing' => 'shipped', 'shipped' => 'delivered'];
            if (! in_array($target, ['processing', 'shipped', 'delivered'], true)
                || ($current->status !== $target && ($next[$current->status] ?? null) !== $target)) {
                $this->reject('Tranziția nu este permisă din starea curentă.');
            }
            if ($current->status !== $target) {
                $data = ['status' => $target];
                if ($target === 'shipped') {
                    $data['shipped_at'] = $current->shipped_at ?? now();
                }
                if ($target === 'delivered') {
                    $data['delivered_at'] = $current->delivered_at ?? now();
                }
                $current->update($data);
            }

            return $current;
        }, 3);
    }

    public function refund(Order $order): Refund
    {
        $current = DB::transaction(function () use ($order): Order {
            CheckoutAttempt::query()->where('order_id', $order->id)->lockForUpdate()->first();
            $current = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($current->payment_method !== 'stripe' || $current->payment_status !== 'paid' || ! $current->stripe_payment_intent
                || $current->hasDispatchHistory()
                || ! in_array($current->status, ['new', 'pending', 'processing'], true)
                || $current->stock_restored_at !== null || $current->stripe_refund_id !== null
                || ! in_array($current->refund_status, [null, 'initiated'], true)
                || $current->returnRequests()->whereNotIn('status', ['rejected'])->exists()) {
                $this->reject('Rambursarea integrală nu mai este permisă. Pentru marfa expediată folosește fluxul de retur.');
            }
            // A durable intent prevents fulfilment racing the HTTP request. If the
            // reply is lost, retry the existing Stripe idempotency key; never clear
            // the intent just because the local caller did not receive a response.
            if ($current->refund_status === null) {
                $current->update(['refund_status' => 'initiated']);
            }

            return $current;
        }, 3);

        return app(StripeService::class)->refundPayment($current);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['lifecycle' => $message]);
    }
}
