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