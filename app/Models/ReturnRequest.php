<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ReturnRequest extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'notes',
        'status',
        'requested_at',
        'approved_at',
        'received_at',
        'stripe_refund_id',
        'refunded_at',
        'stock_restored_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'refunded_at' => 'datetime',
        'stock_restored_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Restaurează stocul produselor returnate o singură dată.
     */
    public function restoreStock(): void
    {
        DB::transaction(function () {
            $return = self::query()
                ->whereKey($this->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($return->stock_restored_at) {
                return;
            }

            $return->loadMissing('order.items');

            foreach ($return->order->items as $item) {
                $product = $item->product()
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    continue;
                }

                $product->increment(
                    'stock_quantity',
                    $item->quantity
                );
            }

            $return->update([
                'stock_restored_at' => now(),
            ]);
        });
    }
}