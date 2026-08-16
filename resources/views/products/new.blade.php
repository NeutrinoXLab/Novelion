@extends('layouts.app')

@section('title', 'Produse noi - Novelion')

@section('content')

<section class="py-16">

    <div class="max-w-7xl mx-auto px-6">

        <div class="mb-10">

            <h1 class="text-3xl font-bold text-slate-900">
                Produse noi
            </h1>

            <p class="mt-2 text-slate-600">
                Descoperă cele mai noi produse adăugate în magazin.
            </p>

        </div>

        @if($products->count())

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

                @foreach($products as $product)

                    <x-product-card :product="$product" />

                @endforeach

            </div>

            <div class="mt-10">
                {{ $products->links() }}
            </div>

        @else

            <div class="bg-white rounded-2xl p-10 text-center shadow-sm">

                <p class="text-slate-600">
                    Momentan nu există produse noi.
                </p>

            </div>

        @endif

    </div>

</section>

@endsection