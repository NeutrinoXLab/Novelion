<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProductStock
{
    public function save(Product $product, array $data, int $originalStock): Product
    {
        return DB::transaction(function () use ($product, $data, $originalStock): Product {
            $current = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            ProductCommercialRules::validate([...$current->getAttributes(), ...$data]);

            if (array_key_exists('stock_quantity', $data)) {
                if ((int) $data['stock_quantity'] === $originalStock) {
                    // An unchanged field is not an inventory adjustment.
                    unset($data['stock_quantity']);
                } elseif ((int) $current->stock_quantity !== $originalStock) {
                    throw ValidationException::withMessages([
                        'data.stock_quantity' => 'Stocul s-a schimbat între timp. Reîncarcă produsul înainte de ajustare.',
                    ]);
                }
            }

            $current->update($data);

            return $current;
        }, 3);
    }
}
