<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $fillable = ['return_request_id', 'order_item_id', 'quantity', 'unit_price', 'line_refund_amount'];

    protected $casts = ['unit_price' => 'decimal:2', 'line_refund_amount' => 'decimal:2'];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
