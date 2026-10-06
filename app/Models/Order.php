<?php

namespace App\Models;

use App\Services\TransactionalEmails;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [

        'user_id',
        'customer_type',

        'order_number',

        /*
        |--------------------------------------------------------------------------
        | Facturare
        |--------------------------------------------------------------------------
        */

        'first_name',
        'last_name',
        'email',
        'phone',

        'county',
        'city',
        'address',
        'postal_code',

        /*
        |--------------------------------------------------------------------------
        | Firmă
        |--------------------------------------------------------------------------
        */

        'company_name',
        'company_vat',
        'company_registration',
        'company_address',
        'company_city',
        'company_county',

        /*
        |--------------------------------------------------------------------------
        | Livrare
        |--------------------------------------------------------------------------
        */

        'shipping_first_name',
        'shipping_last_name',
        'shipping_phone',

        'shipping_county',
        'shipping_city',
        'shipping_address',
        'shipping_postal_code',

        /*
        |--------------------------------------------------------------------------
        | Totaluri
        |--------------------------------------------------------------------------
        */

        'subtotal',
        'shipping_cost',
        'total',

        /*
        |--------------------------------------------------------------------------
        | Plată
        |--------------------------------------------------------------------------
        */

        'payment_method',
        'payment_status',
        'courier',
        'awb_number',
        'tracking_url',
        'shipped_at',

        'stripe_session_id',
        'stripe_payment_intent',
        'late_stripe_payment_at',
        'stripe_reconcile_claim',
        'stripe_reconcile_next_at',
        'stripe_reconcile_checked_at',
        'stripe_reconcile_failures',
        'stripe_reconcile_result',
        'stripe_reconcile_cursor',
        'stripe_refund_id',
        'refund_status',
        'refunded_at',

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        'status',
        'stock_restored_at',
        'delivered_at',
        'notes',
    ];

    protected $casts = [
        'stripe_reconcile_next_at' => 'datetime',
        'stripe_reconcile_checked_at' => 'datetime',
        'stripe_reconcile_cursor' => 'array',
        'delivered_at' => 'datetime',
        'late_stripe_payment_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Evenimente Eloquent.
     */
    protected static function booted(): void
    {
        static::updated(function (Order $order) {

            if ($order->wasChanged('refund_status') && $order->refund_status) {
                app(TransactionalEmails::class)->refund($order);
            }

            if (! $order->wasChanged('status')) {
                return;
            }

            if (in_array($order->status, ['shipped', 'delivered', 'cancelled'], true)) {
                app(TransactionalEmails::class)->order($order, $order->status);
            }
        });
    }

    /**
     * Produsele din comandă.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Utilizatorul care a plasat comanda.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Solicitările de retur ale comenzii.
     */
    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }
}
