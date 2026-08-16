@extends('layouts.app')

@section('title', 'Categorii - Novelion')

@section('content')

<section class="py-16">

    <div class="max-w-7xl mx-auto px-6">

        <div class="text-center mb-10">

            <h1 class="text-3xl font-bold text-slate-900">
                Categorii
            </h1>

            <p class="mt-3 text-slate-600">
                Descoperă produsele Novelion organizate pe categorii.
            </p>

        </div>

        @if($categories->count())

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

                @foreach($categories as $category)

                    <a
                        href="{{ route('categories.show', $category) }}"
                        class="group block bg-white rounded-2xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-300">

                        <div class="aspect-square overflow-hidden bg-slate-100">

                            @if($category->image_path)

                                <img
                                    src="{{ asset('storage/' . $category->image_path) }}"
                                    alt="{{ $category->name }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-500">

                            @else

                                <div class="w-full h-full flex items-center justify-center text-6xl">
                                    📦
                                </div>

                            @endif

                        </div>

                        <div class="p-5 text-center">

                            <h2 class="font-semibold text-lg text-slate-800 group-hover:text-cyan-600 transition">
                                {{ $category->name }}
                            </h2>

                        </div>

                    </a>

                @endforeach

            </div>

        @else

            <div class="bg-white rounded-2xl p-10 text-center shadow-sm">

                <p class="text-slate-600">
                    Momentan nu există categorii disponibile.
                </p>

            </div>

        @endif

    </div>

</section>

@endsection