<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShippingRate;

class CartService
{
    protected string $sessionKey = 'cart';

    /**
     * Toate produsele din coș.
     */
    public function getCart(): array
    {
        $cart = session()->get($this->sessionKey, []);

        foreach ($cart as $key => &$item) {

            $product = Product::find($item['id']);

            if (! $product || ! $product->is_active) {
                unset($cart[$key]);

                continue;
            }

            $item['product'] = $product;

            $item['price'] = $product->sale_price ?: $product->selling_price;

            $item['subtotal'] = $item['price'] * $item['quantity'];

            /*
             * Greutatea totală a acestui produs
             * în funcție de cantitatea comandată.
             */
            $item['total_weight'] = $product->weight === null
                ? null
                : (float) $product->weight * $item['quantity'];

            /*
             * Volumul unei bucăți:
             *
             * lungime × lățime × înălțime
             *
             * Dimensiunile sunt în centimetri,
             * rezultatul este în cm³.
             */
            $item['unit_volume'] =
                ($product->length ?? 0)
                * ($product->width ?? 0)
                * ($product->height ?? 0);

            /*
             * Volumul total pentru cantitatea comandată.
             */
            $item['total_volume'] =
                $item['unit_volume'] * $item['quantity'];
        }

        return $cart;
    }

    /**
     * Adaugă produs.
     */
    public function add(Product $product, int $quantity = 1): void
    {
        if (! $product->is_active) {
            throw new \Exception(
                'Acest produs nu mai este disponibil.'
            );
        }

        $cart = session()->get($this->sessionKey, []);

        if (isset($cart[$product->id])) {

            $cart[$product->id]['quantity'] += $quantity;

        } else {

            $cart[$product->id] = [
                'id' => $product->id,
                'quantity' => $quantity,
            ];
        }

        session()->put($this->sessionKey, $cart);
    }

    /**
     * Actualizează cantitatea.
     */
    public function update(Product $product, int $quantity): void
    {
        $cart = session()->get($this->sessionKey, []);

        if (isset($cart[$product->id])) {

            $cart[$product->id]['quantity'] = max(1, $quantity);
        }

        session()->put($this->sessionKey, $cart);
    }

    /**
     * Elimină produs.
     */
    public function remove(Product $product): void
    {
        $cart = session()->get($this->sessionKey, []);

        unset($cart[$product->id]);

        session()->put($this->sessionKey, $cart);
    }

    /**
     * Golește coșul.
     */
    public function clear(): void
    {
        session()->forget($this->sessionKey);
    }

    /**
     * Numărul total de produse.
     */
    public function count(): int
    {
        return collect($this->getCart())->sum('quantity');
    }

    /**
     * Subtotal produse.
     */
    public function subtotal(): float
    {
        return (float) collect($this->getCart())
            ->sum('subtotal');
    }

    /**
     * Greutatea totală a comenzii.
     */
    public function totalWeight(): float
    {
        return (float) collect($this->getCart())
            ->sum('total_weight');
    }

    public function hasMissingWeight(): bool
    {
        return collect($this->getCart())->contains(fn ($item) => $item['total_weight'] === null || $item['total_weight'] <= 0);
    }

    public function canShip(): bool
    {
        return ! $this->hasMissingWeight() && $this->shippingRate() !== null;
    }

    /**
     * Volumul total al comenzii.
     */
    public function totalVolume(): float
    {
        return (float) collect($this->getCart())
            ->sum('total_volume');
    }

    /**
     * Găsește regula de transport potrivită.
     *
     * Regula trebuie să poată acomoda atât greutatea,
     * cât și volumul total al comenzii.
     */
    public function shippingRate(): ?ShippingRate
    {
        if ($this->hasMissingWeight()) {
            return null;
        }
        $weight = $this->totalWeight();
        $volume = $this->totalVolume();

        return ShippingRate::query()
            ->where('is_active', true)
            ->where(function ($query) use ($weight) {
                $query
                    ->whereNull('max_weight')
                    ->orWhere('max_weight', '>=', $weight);
            })
            ->where(function ($query) use ($volume) {
                $query
                    ->whereNull('max_volume')
                    ->orWhere('max_volume', '>=', $volume);
            })
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Prețul transportului.
     */
    public function shippingCost(): float
    {
        $rate = $this->shippingRate();

        return $rate ? (float) $rate->price : 0;
    }

    /**
     * Numele categoriei de transport.
     */
    public function shippingName(): ?string
    {
        return $this->shippingRate()?->name;
    }

    /**
     * Total comandă:
     *
     * produse + transport.
     */
    public function total(): float
    {
        return $this->subtotal() + $this->shippingCost();
    }
}
