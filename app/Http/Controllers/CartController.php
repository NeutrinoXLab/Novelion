<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Afișează coșul.
     */
    public function index(CartService $cart)
    {
        return view('cart.index', [
    'items' => $cart->getCart(),
    'subtotal' => $cart->subtotal(),
    'shippingCost' => $cart->shippingCost(),
    'shippingName' => $cart->shippingName(),
    'total' => $cart->total(),
    'count' => $cart->count(),
]);
    }

    /**
     * Adaugă produs în coș.
     */
    public function add(
        Request $request,
        Product $product,
        CartService $cart
    ) {
        $quantity = max(
            1,
            (int) $request->input('quantity', 1)
        );

        // Cantitatea existentă în coș.
        $currentQuantity = 0;

        foreach ($cart->getCart() as $item) {

            if ($item['id'] == $product->id) {
                $currentQuantity = $item['quantity'];
                break;
            }
        }

        $requestedQuantity = $currentQuantity + $quantity;

        // Verificăm stocul disponibil.
        if ($requestedQuantity > $product->stock_quantity) {

            return redirect()
                ->back()
                ->with(
                    'error',
                    "Nu poți adăuga {$requestedQuantity} buc. din \"{$product->name}\". În stoc mai sunt doar {$product->stock_quantity}."
                );
        }

        $cart->add($product, $quantity);

        return redirect()
            ->back()
            ->with(
                'success',
                'Produsul a fost adăugat în coș.'
            );
    }

    /**
     * Actualizează cantitatea.
     */
    public function update(
        Request $request,
        Product $product,
        CartService $cart
    ) {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $quantity = (int) $request->quantity;

        // Verificăm stocul.
        if ($quantity > $product->stock_quantity) {

            return redirect()
                ->route('cart.index')
                ->with(
                    'error',
                    "Nu există suficiente produse în stoc. Disponibil: {$product->stock_quantity} buc."
                );
        }

        $cart->update($product, $quantity);

        return redirect()
            ->route('cart.index')
            ->with(
                'success',
                'Cantitatea a fost actualizată.'
            );
    }

    /**
     * Elimină produsul.
     */
    public function remove(
        Product $product,
        CartService $cart
    ) {
        $cart->remove($product);

        return redirect()
            ->route('cart.index')
            ->with(
                'success',
                'Produsul a fost eliminat din coș.'
            );
    }

    /**
     * Golește coșul.
     */
    public function clear(CartService $cart)
    {
        $cart->clear();

        return redirect()
            ->route('cart.index')
            ->with(
                'success',
                'Coșul a fost golit.'
            );
    }
}