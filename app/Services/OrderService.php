<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * Creează comanda și rezervă stocul.
     */
    public function create(array $data, CartService $cart): Order
    {
        return DB::transaction(function () use ($data, $cart) {

            /*
            |--------------------------------------------------------------------------
            | Verificăm stocul
            |--------------------------------------------------------------------------
            */

            foreach ($cart->getCart() as $item) {

                $product = $item['product'];

                if ($item['quantity'] > $product->stock_quantity) {
                    throw new \Exception(
                        "Produsul {$product->name} nu mai are suficient stoc."
                    );
                }
            }

            $isCompany = ($data['customer_type'] ?? 'individual') === 'company';

            /*
            |--------------------------------------------------------------------------
            | Calculăm separat produsele și transportul
            |--------------------------------------------------------------------------
            */

            $subtotal = $cart->subtotal();
            $shippingCost = $cart->shippingCost();
            $shippingName = $cart->shippingName();
            $total = $subtotal + $shippingCost;

            /*
            |--------------------------------------------------------------------------
            | Creăm comanda
            |--------------------------------------------------------------------------
            */

            $order = Order::create([

                'user_id' => Auth::id(),

                'customer_type' => $data['customer_type'] ?? 'individual',

                'order_number' =>
                    'NOV-' .
                    now()->format('Ymd') .
                    '-' .
                    strtoupper(Str::random(6)),

                /*
                |--------------------------------------------------------------------------
                | FACTURARE
                |--------------------------------------------------------------------------
                */

                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],

                'county' => $data['county'],
                'city' => $data['city'],
                'address' => $data['address'],
                'postal_code' => $data['postal_code'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | DATE FIRMĂ
                |--------------------------------------------------------------------------
                */

                'company_name' =>
                    $isCompany ? $data['company_name'] : null,

                'company_vat' =>
                    $isCompany ? $data['company_vat'] : null,

                'company_registration' =>
                    $isCompany ? $data['company_registration'] : null,

                'company_address' =>
                    $isCompany ? $data['company_address'] : null,

                'company_city' =>
                    $isCompany ? $data['company_city'] : null,

                'company_county' =>
                    $isCompany ? $data['company_county'] : null,

                /*
                |--------------------------------------------------------------------------
                | ADRESĂ LIVRARE
                |--------------------------------------------------------------------------
                */

                'shipping_first_name' =>
                    $data['shipping_first_name'],

                'shipping_last_name' =>
                    $data['shipping_last_name'],

                'shipping_phone' =>
                    $data['shipping_phone'],

                'shipping_county' =>
                    $data['shipping_county'],

                'shipping_city' =>
                    $data['shipping_city'],

                'shipping_address' =>
                    $data['shipping_address'],

                'shipping_postal_code' =>
                    $data['shipping_postal_code'],

                /*
                |--------------------------------------------------------------------------
                | TOTALURI
                |--------------------------------------------------------------------------
                */

                'subtotal' => $subtotal,

                'shipping_cost' => $shippingCost,

                'total' => $total,

                /*
                |--------------------------------------------------------------------------
                | PLATĂ
                |--------------------------------------------------------------------------
                */

                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',

                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                */

                'status' => 'pending',

                'notes' => $data['notes'] ?? null,

            ]);

            /*
            |--------------------------------------------------------------------------
            | Rezervăm stocul imediat
            |--------------------------------------------------------------------------
            */

            foreach ($cart->getCart() as $item) {

                $item['product']->decrement(
                    'stock_quantity',
                    $item['quantity']
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Produsele din comandă
            |--------------------------------------------------------------------------
            */

            foreach ($cart->getCart() as $item) {

                $price =
                    $item['product']->sale_price
                    ?: $item['product']->selling_price;

                OrderItem::create([

                    'order_id' => $order->id,

                    'product_id' => $item['product']->id,

                    'product_name' => $item['product']->name,

                    'price' => $price,

                    'quantity' => $item['quantity'],

                    'total' =>
                        $price * $item['quantity'],

                ]);
            }

            return $order;
        });
    }

    /**
     * Marchează comanda ca plătită.
     */
    public function markAsPaid(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }

        $order->update([
            'payment_status' => 'paid',
            'status' => 'processing',
        ]);
    }

    /**
     * Marchează plata ca eșuată.
     */
    public function markAsFailed(Order $order): void
    {
        $order->update([
            'payment_status' => 'failed',
            'status' => 'cancelled',
        ]);
    }

    /**
     * Anulează comanda și reface stocul.
     */
    public function cancel(Order $order): void
    {
        DB::transaction(function () use ($order) {

            /*
             * Luăm statusul real din baza de date.
             *
             * Este important deoarece această metodă este apelată
             * înainte ca Filament să salveze noul status.
             */
            $currentStatus = $order->getRawOriginal('status');

            /*
             * Dacă această comandă este deja anulată,
             * nu mai refacem stocul.
             */
            if ($currentStatus === 'cancelled') {
                return;
            }

            $order->load('items.product');

            /*
             * Refacem stocul.
             */
            foreach ($order->items as $item) {

                if (! $item->product) {
                    continue;
                }

                $item->product->increment(
                    'stock_quantity',
                    $item->quantity
                );
            }

            /*
             * Păstrăm statusul plății dacă plata Stripe
             * a fost deja efectuată.
             */
            $paymentStatus = $order->payment_status;

            /*
             * Pentru ramburs, dacă plata nu a fost făcută,
             * anularea marchează plata ca eșuată.
             */
            if (
                $order->payment_method === 'cash' &&
                $order->payment_status === 'pending'
            ) {
                $paymentStatus = 'failed';
            }

            $order->update([
                'status' => 'cancelled',
                'payment_status' => $paymentStatus,
            ]);
        });
    }
}