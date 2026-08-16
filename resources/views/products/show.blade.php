@extends('layouts.app')

@section('title', $product->name)

@section('content')

<section class="bg-white py-12">

    <div class="max-w-7xl mx-auto px-6">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">

            @include('products.partials.gallery')

            @include('products.partials.info')

        </div>

        {{-- Recenzii --}}
        <x-product-reviews :product="$product" />

    </div>

</section>

@include('products.partials.related')

@endsection