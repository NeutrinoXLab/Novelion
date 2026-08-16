@extends('layouts.app')

@section('title', 'Favorite')

@section('content')

<section class="max-w-7xl mx-auto px-6 py-12">

    <div class="mb-10">

        <h1 class="text-4xl font-bold text-slate-900">

            ❤️ Produsele mele favorite

        </h1>

        <p class="mt-2 text-slate-500">

            Produsele salvate pentru mai târziu.

        </p>

    </div>

    @if($products->count())

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">

            @foreach($products as $product)

                <x-product-card :product="$product" />

            @endforeach

        </div>

    @else

        <div
            class="bg-white rounded-3xl shadow border border-slate-200 p-16 text-center">

            <div class="text-7xl mb-6">

                💔

            </div>

            <h2 class="text-3xl font-bold text-slate-900">

                Nu ai produse favorite

            </h2>

            <p class="mt-4 text-slate-500">

                Adaugă produse la Favorite și le vei găsi aici.

            </p>

            <a
                href="{{ route('home') }}"
                class="inline-block mt-8 bg-cyan-500 hover:bg-cyan-600 text-white px-8 py-4 rounded-2xl font-semibold transition">

                Vezi produsele

            </a>

        </div>

    @endif

</section>

@endsection