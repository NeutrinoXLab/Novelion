<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Afișează produsele favorite.
     */
    public function index()
    {
        $products = auth()->user()
            ->wishlist()
            ->with('primaryImage', 'brand')
            ->latest()
            ->get();

        return view('wishlist.index', compact('products'));
    }

    /**
     * Adaugă / Elimină un produs din Favorite.
     */
    public function toggle(Product $product)
    {
        $user = auth()->user();

        if ($user->wishlist()->where('product_id', $product->id)->exists()) {

            $user->wishlist()->detach($product->id);

            return back()->with(
                'success',
                'Produsul a fost eliminat din Favorite.'
            );
        }

        $user->wishlist()->attach($product->id);

        return back()->with(
            'success',
            'Produsul a fost adăugat la Favorite.'
        );
    }
}