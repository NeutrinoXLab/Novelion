@extends('layouts.app')

@section('title', $category->name)

@section('content')

<section class="py-20 bg-white">

    <div class="max-w-7xl mx-auto px-6">

        <div class="mb-12">

            <h1 class="text-4xl font-bold text-slate-900">
                {{ $category->name }}
            </h1>

            @if($category->description)

                <p class="mt-4 text-lg text-slate-600">
                    {{ $category->description }}
                </p>

            @endif

        </div>

        @if($products->count())

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">

                @foreach($products as $product)

                    <x-product-card :product="$product" />

                @endforeach

            </div>

            <div class="mt-12">

                {{ $products->links() }}

            </div>

        @else

            <div class="text-center py-24">

                <h2 class="text-2xl font-semibold text-slate-700">
                    Nu există produse în această categorie.
                </h2>

            </div>

        @endif

    </div>

</section>

@endsection