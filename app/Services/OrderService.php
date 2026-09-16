<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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
        if (! $cart->canShip()) {
            throw new \RuntimeException('Nu există o regulă validă de transport pentru coș.');
        }

        return DB::transaction(function () use ($data, $cart) {

            /*
            |--------------------------------------------------------------------------
            | Verificăm stocul
            |--------------------------------------------------------------------------
            */

            $quantities = [];

            foreach ($cart->getCart() as $item) {
                $productId = $item['product']->id;
                $quantity = $item['quantity'];

                if (! is_int($quantity) || $quantity < 1) {
                    throw new \RuntimeException('Cantitatea din coș este invalidă.');
                }

                $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
            }

            ksort($quantities);
            $lockedItems = [];

            foreach ($quantities as $productId => $quantity) {
                $product = Product::query()->whereKey($productId)
                    ->lockForUpdate()->firstOrFail();

                if (! $product->is_active || $quantity > $product->stock_quantity) {
                    throw new \Exception(
                        "Produsul {$product->name} nu mai are suficient stoc."
                    );
                }

                $price = $product->sale_price ?: $product->selling_price;
                $lockedItems[] = compact('product', 'quantity', 'price');
            }

            $isCompany = ($data['customer_type'] ?? 'individual') === 'company';

            /*
            |--------------------------------------------------------------------------
            | Calculăm separat produsele și transportul
            |--------------------------------------------------------------------------
            */

            $subtotal = (float) collect($lockedItems)
                ->sum(fn (array $item): float => (float) $item['price'] * $item['quantity']);
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

                'order_number' => 'NOV-'.
                    now()->format('Ymd').
                    '-'.
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

                'company_name' => $isCompany ? $data['company_name'] : null,

                'company_vat' => $isCompany ? $data['company_vat'] : null,

                'company_registration' => $isCompany ? ($data['company_registration'] ?? null) : null,

                'company_address' => $isCompany ? $data['company_address'] : null,

                'company_city' => $isCompany ? $data['company_city'] : null,

                'company_county' => $isCompany ? $data['company_county'] : null,

                /*
                |--------------------------------------------------------------------------
                | ADRESĂ LIVRARE
                |--------------------------------------------------------------------------
                */

                'shipping_first_name' => $data['shipping_first_name'],

                'shipping_last_name' => $data['shipping_last_name'],

                'shipping_phone' => $data['shipping_phone'],

                'shipping_county' => $data['shipping_county'],

                'shipping_city' => $data['shipping_city'],

                'shipping_address' => $data['shipping_address'],

                'shipping_postal_code' => $data['shipping_postal_code'],

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

            foreach ($lockedItems as $item) {

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

            foreach ($lockedItems as $item) {

                $price = $item['price'];

                OrderItem::create([

                    'order_id' => $order->id,

                    'product_id' => $item['product']->id,

                    'product_name' => $item['product']->name,

                    'price' => $price,

                    'quantity' => $item['quantity'],

                    'total' => $price * $item['quantity'],

                ]);
            }

            return $order;
        });
    }

    /**
     * Marchează comanda ca plătită.
     */
    public function markAsPaid(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->payment_status === 'paid') {
                return false;
            }

            /*
             * Un refund confirmat nu poate fi transformat ulterior într-o
             * plată reușită de un webhook întârziat.
             */
            if ($order->payment_status === 'refunded' || $order->status === 'cancelled') {
                return false;
            }

            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing',
                'stock_restored_at' => null,
            ]);

            return true;
        });
    }

    /**
     * Restaurează stocul unei comenzi o singură dată.
     *
     * Folosește lockForUpdate() pentru a preveni
     * restaurarea dublă în cazul webhook-urilor repetate
     * sau al mai multor acțiuni simultane.
     */
    public function restoreStock(Order $order): void
    {
        DB::transaction(function () use ($order) {

            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Dacă stocul a fost deja restaurat,
             * nu mai facem nimic.
             */
            if ($order->stock_restored_at !== null) {
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
             * Marcăm restaurarea ca efectuată.
             */
            $order->update([
                'stock_restored_at' => now(),
            ]);
        });
    }

    /**
     * Marchează plata ca eșuată și reface stocul.
     */
    public function markAsFailed(Order $order): void
    {
        DB::transaction(function () use ($order) {

            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Webhook-urile pot ajunge în orice ordine. O expirare sau o
             * eșuare venită târziu nu trebuie să anuleze o plată confirmată.
             */
            if (in_array($order->payment_status, ['paid', 'refunded'], true)) {
                return;
            }

            $order->update([
                'payment_status' => 'failed',
                'status' => 'cancelled',
            ]);

            $this->restoreStock($order);
        });
    }

    /**
     * Anulează comanda și reface stocul.
     *
     * O comandă deja plătită nu poate fi anulată direct.
     * Pentru aceasta trebuie efectuat mai întâi refund-ul Stripe.
     */
    public function cancel(Order $order): void
    {
        DB::transaction(function () use ($order) {

            /*
             * Luăm starea actuală din baza de date.
             *
             * Este important deoarece această metodă este apelată
             * înainte ca Filament să salveze noul status.
             */
            $order->refresh();

            $currentStatus = $order->status;

            /*
             * O comandă deja plătită trebuie rambursată înainte
             * de a putea fi anulată.
             */
            if ($order->payment_status === 'paid') {
                throw new \RuntimeException(
                    'Comanda este deja plătită și trebuie rambursată înainte de anulare.'
                );
            }

            /*
             * Pentru ramburs, dacă plata nu a fost făcută,
             * anularea marchează plata ca eșuată.
             */
            $paymentStatus = $order->payment_status;

            if (
                $order->payment_method === 'cash' &&
                $order->payment_status === 'pending'
            ) {
                $paymentStatus = 'failed';
            }

            /*
             * Dacă această comandă este deja anulată,
             * nu mai schimbăm statusul.
             */
            if ($currentStatus !== 'cancelled') {
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => $paymentStatus,
                ]);
            }

            /*
             * Refacem stocul.
             *
             * restoreStock() este idempotent:
             * dacă stocul a fost deja restaurat, nu îl adaugă din nou.
             */
            $this->restoreStock($order);
        });
    }
}
