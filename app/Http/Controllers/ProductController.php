<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Toate produsele.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->where('is_active', true)
            ->with([
                'primaryImage',
                'brand',
                'category',
            ]);

        $products = $query
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get();

        $brands = Brand::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('products.index', compact(
            'products',
            'categories',
            'brands'
        ));
    }


    /**
     * Produse noi.
     */
    public function newProducts()
    {
        $products = Product::query()
            ->where('is_active', true)
            ->where('is_new', true)
            ->with([
                'primaryImage',
                'brand',
                'category',
            ])
            ->latest()
            ->paginate(12);

        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get();

        $brands = Brand::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('products.index', compact(
            'products',
            'categories',
            'brands'
        ));
    }


    /**
     * Produse aflate la promoție.
     */
    public function promotions()
    {
        $products = Product::query()
            ->where('is_active', true)
            ->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->with([
                'primaryImage',
                'brand',
                'category',
            ])
            ->latest()
            ->paginate(12);

        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get();

        $brands = Brand::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('products.index', compact(
            'products',
            'categories',
            'brands'
        ));
    }


    /**
     * Afișează pagina unui produs.
     */
    public function show(Product $product)
    {
        $product->load([
            'images',
            'brand',
            'category',
        ]);

        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->with('primaryImage')
            ->take(4)
            ->get();

        return view('products.show', compact(
            'product',
            'relatedProducts'
        ));
    }
}