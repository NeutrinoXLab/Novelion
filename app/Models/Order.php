<?php

namespace App\Models;

use App\Mail\OrderCancelledMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderShippedMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;

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
        'delivered_at' => 'datetime',
    ];

    /**
     * Evenimente Eloquent.
     */
    protected static function booted(): void
    {
        static::updated(function (Order $order) {

            if (! $order->wasChanged('status')) {
                return;
            }

            switch ($order->status) {

                case 'shipped':

                    Mail::to($order->email)
                        ->send(new OrderShippedMail($order));

                    break;

                case 'delivered':

                    Mail::to($order->email)
                        ->send(new OrderDeliveredMail($order));

                    break;

                case 'cancelled':

                    Mail::to($order->email)
                        ->send(new OrderCancelledMail($order));

                    break;
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