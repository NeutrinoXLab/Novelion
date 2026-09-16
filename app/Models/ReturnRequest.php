<?php

namespace App\Models;

use App\Mail\RefundStatusMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReturnRequest extends Model
{
    protected static function booted(): void
    {
        static::updated(function (ReturnRequest $return): void {
            if ($return->wasChanged('refund_status') && $return->refund_status) {
                Mail::to($return->order->email)->send(new RefundStatusMail($return->order, $return->refund_status, $return->refund_amount));
            }
        });
    }

    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'reason',
        'notes',
        'status',
        'requested_at',
        'approved_at',
        'received_at',
        'stripe_refund_id',
        'refund_method',
        'refund_status',
        'refund_amount',
        'refund_currency',
        'bank_iban',
        'bank_transfer_accepted_at',
        'confirmation_sent_at',
        'refunded_at',
        'stock_restored_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'refunded_at' => 'datetime',
        'stock_restored_at' => 'datetime',
        'bank_transfer_accepted_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
        'refund_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReturnPhoto::class);
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

            $return->loadMissing('order.items', 'items.orderItem');

            $items = $return->items->isNotEmpty()
                ? $return->items->map(fn (ReturnItem $item) => (object) [
                    'product' => $item->orderItem?->product,
                    'quantity' => $item->quantity,
                ])
                : $return->order->items;

            foreach ($items as $item) {
                if ($return->items->isNotEmpty()) {
                    $product = $item->product;
                    if ($product) {
                        $product = Product::query()->whereKey($product->id)->lockForUpdate()->first();
                    }
                } else {
                    $product = $item->product()
                        ->lockForUpdate()
                        ->first();
                }

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
