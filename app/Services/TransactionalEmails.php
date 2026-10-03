<?php

namespace App\Services;

use App\Mail\OrderCancelledMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderPaidMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderShippedMail;
use App\Mail\RefundStatusMail;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\TransactionalEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalEmails
{
    private const MAX_ATTEMPTS = 5;

    private const BACKOFF_SECONDS = [60, 300, 900, 3600];

    public function order(Order $order, string $kind): void
    {
        $this->record('order:'.$order->id.':'.$kind, $order->id, $kind);
    }

    public function refund(Order|ReturnRequest $record): void
    {
        $isReturn = $record instanceof ReturnRequest;
        $this->record(
            ($isReturn ? 'return:' : 'order:').$record->id.':refund:'.$record->refund_status,
            $isReturn ? $record->order_id : $record->id,
            'refund',
            ['state' => $record->refund_status, 'amount' => (string) ($isReturn ? $record->refund_amount : $record->total)],
            $isReturn ? $record->id : null,
        );
    }

    private function record(string $key, int $orderId, string $kind, array $payload = [], ?int $returnId = null): void
    {
        // A business transaction rolls this intent back together with its state.
        TransactionalEmail::query()->firstOrCreate(['event_key' => $key], [
            'order_id' => $orderId,
            'return_request_id' => $returnId,
            'kind' => $kind,
            'payload' => $payload,
            'next_attempt_at' => now(),
        ]);
    }

    public function deliver(int $id): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Transactional mail delivery must run outside business transactions.');
        }

        // This transaction locks only the delivery receipt, never business state.
        // Concurrent drainers re-check sent_at after obtaining the same row lock.
        DB::transaction(function () use ($id): void {
            $email = TransactionalEmail::query()->whereKey($id)->lockForUpdate()->first();
            if (! $email || $email->sent_at || $email->failed_at || $email->next_attempt_at->isFuture()) {
                return;
            }

            try {
                $order = Order::query()->with('items')->findOrFail($email->order_id);
                $mail = match ($email->kind) {
                    'placed' => new OrderPlacedMail($order),
                    'paid' => new OrderPaidMail($order),
                    'shipped' => new OrderShippedMail($order),
                    'delivered' => new OrderDeliveredMail($order),
                    'cancelled' => new OrderCancelledMail($order),
                    'refund' => new RefundStatusMail($order, $email->payload['state'], $email->payload['amount']),
                    default => throw new \LogicException('Unknown transactional email kind.'),
                };
                Mail::to($order->email)->send($mail);
            } catch (\Throwable $exception) {
                $attempts = $email->attempts + 1;
                $email->update([
                    'attempts' => $attempts,
                    'last_error_type' => $exception::class,
                    'next_attempt_at' => now()->addSeconds(self::BACKOFF_SECONDS[min($attempts - 1, 3)]),
                    'failed_at' => $attempts >= self::MAX_ATTEMPTS ? now() : null,
                ]);
                // Do not log exception messages, addresses, bodies or SMTP credentials.
                Log::warning('Transactional email delivery failed.', [
                    'delivery_id' => $email->id,
                    'kind' => $email->kind,
                    'attempt' => $attempts,
                    'error_type' => $exception::class,
                ]);

                return;
            }

            $email->update(['attempts' => $email->attempts + 1, 'sent_at' => now(), 'last_error_type' => null]);
        });
    }
}
