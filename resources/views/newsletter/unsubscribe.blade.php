@extends('layouts.app')

@section('title', 'Dezabonare newsletter - Novelion')

@section('content')

<section class="min-h-[60vh] bg-slate-100 py-20">

<div class="max-w-2xl mx-auto px-6">

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-10 md:p-14 text-center">

        @if($success)

            <div class="text-6xl mb-6">
                ✅
            </div>

            <h1 class="text-3xl md:text-4xl font-bold text-slate-900">
                Dezabonare reușită
            </h1>

            <p class="mt-5 text-lg text-slate-600 leading-8">
                {{ $message }}
            </p>

            <a
                href="{{ route('home') }}"
                class="inline-flex items-center justify-center mt-8 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl px-6 py-3 font-semibold transition">

                Înapoi la Novelion

            </a>

        @else

            <div class="text-6xl mb-6">
                ⚠️
            </div>

            <h1 class="text-3xl md:text-4xl font-bold text-slate-900">
                Link indisponibil
            </h1>

            <p class="mt-5 text-lg text-slate-600 leading-8">
                {{ $message }}
            </p>

            <a
                href="{{ route('home') }}"
                class="inline-flex items-center justify-center mt-8 bg-slate-900 hover:bg-slate-800 text-white rounded-xl px-6 py-3 font-semibold transition">

                Înapoi la Novelion

            </a>

        @endif

    </div>

</div>
</section>

@endsection
