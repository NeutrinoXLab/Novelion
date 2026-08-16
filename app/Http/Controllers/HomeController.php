<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Homepage.
     */
    public function index()
    {
        $categories = Category::where('is_active', true)
            ->withCount('products')
            ->orderBy('name')
            ->take(6)
            ->get();

        $featuredProducts = Product::where('is_active', true)
            ->latest()
            ->take(4)
            ->get();

        $latestProducts = Product::where('is_active', true)
            ->latest()
            ->take(8)
            ->get();

        return view('home', compact(
            'categories',
            'featuredProducts',
            'latestProducts'
        ));
    }

    /**
     * Căutare live produse.
     */
    public function search(Request $request)
    {
        $query = trim($request->get('q'));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Product::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->with('primaryImage')
            ->take(8)
            ->get();

        $products = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'selling_price' => $product->selling_price,
                'sale_price' => $product->sale_price,
                'primary_image_path' => $product->primary_image_path,
            ];
        });

        return response()->json($products);
    }
}