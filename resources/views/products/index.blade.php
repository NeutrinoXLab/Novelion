@extends('layouts.app')

@section('title', 'Toate produsele')

@section('content')

<section class="py-20 bg-white">

    <div class="max-w-7xl mx-auto px-6">

        <div class="flex justify-between items-center mb-10">

            <div>

                <h1 class="text-4xl font-bold text-slate-900">
                    Toate produsele
                </h1>

                <p class="mt-2 text-slate-600">
                    Descoperă întreaga gamă de produse disponibile în magazin.
                </p>

            </div>

            <span class="text-slate-500">
                {{ $products->total() }} produse
            </span>

        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">

            @forelse($products as $product)

                <x-product-card :product="$product" />

            @empty

                <div class="col-span-4 text-center py-20">

                    <h2 class="text-2xl font-semibold text-slate-700">
                        Nu există produse.
                    </h2>

                </div>

            @endforelse

        </div>

        <div class="mt-12">

            {{ $products->links() }}

        </div>

    </div>

</section>

@endsection