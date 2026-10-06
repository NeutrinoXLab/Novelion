<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

class ProductCommercialRules
{
    public const MAX_STOCK = 2147483647;

    public const MAX_MONEY = 99999999.99;

    public static function validateRestoration(mixed $stock, mixed $quantity): void
    {
        Validator::make(['stock' => $stock, 'quantity' => $quantity], [
            'stock' => ['required', 'integer', 'min:0', 'max:'.self::MAX_STOCK],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_STOCK],
        ])->validate();
        if ((int) $stock > self::MAX_STOCK - (int) $quantity) {
            throw new \RuntimeException('Restaurarea ar produce un stoc invalid.');
        }
    }

    public static function validate(array $attributes): void
    {
        Validator::make($attributes, [
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:'.self::MAX_STOCK],
            'purchase_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_MONEY],
            'selling_price' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_MONEY],
            'sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_MONEY],
        ])->validate();
    }
}
