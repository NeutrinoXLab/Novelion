<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Salvează o recenzie nouă.
     */
    public function store(Request $request, Product $product)
    {
        $verifiedPurchase = $request->user()->orders()
            ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->exists();

        abort_unless($verifiedPurchase, 403, 'Recenziile pot fi publicate numai pentru produse cumpărate de la Novelion.');

        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:10'],
        ]);

        try {

            Review::create([
                'user_id' => auth()->id(),
                'product_id' => $product->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'is_approved' => false,
                'is_verified_purchase' => true,
            ]);

            return back()->with(
                'success',
                'Recenzia a fost trimisă și așteaptă aprobarea administratorului.'
            );

        } catch (\Throwable $e) {

            report($e);

            return back()->with(
                'error',
                'Recenzia nu a putut fi trimisă. Te rugăm să încerci din nou.'
            );

        }
    }
}
