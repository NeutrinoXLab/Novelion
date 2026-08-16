@extends('layouts.app')

@section('title', 'Contact - Novelion')

@section('content')

<div class="bg-slate-100 py-16">

    <div class="max-w-5xl mx-auto px-6">

        {{-- Titlu --}}
        <div class="text-center mb-12">

            <p class="text-cyan-600 font-semibold uppercase tracking-widest text-sm">
                Contact
            </p>

            <h1 class="mt-3 text-4xl md:text-5xl font-bold text-slate-900">
                Suntem aici pentru tine
            </h1>

            <p class="mt-5 text-lg text-slate-600 max-w-3xl mx-auto leading-8">
                Ai o întrebare despre un produs, o comandă sau livrare?
                Ne poți contacta folosind datele de mai jos.
            </p>

        </div>


        {{-- Date contact --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

            {{-- Telefon --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    📞
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    Telefon
                </h2>

                <p class="mt-2 text-slate-600">
                    0750 444 672
                </p>

                <p class="mt-2 text-sm text-slate-500">
                    Luni - Vineri, 09:00 - 17:00
                </p>

            </div>


            {{-- Email --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    ✉️
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    Email
                </h2>

                <p class="mt-2 text-slate-600 break-all">
                    novelionprime@gmail.com
                </p>

                <p class="mt-2 text-sm text-slate-500">
                    Îți vom răspunde cât mai curând posibil.
                </p>

            </div>


            {{-- Adresă --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    📍
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    Sediu
                </h2>

                <p class="mt-2 text-slate-600 leading-7">
                    NOVELION S.R.L.<br>
                    Str. Daciei nr. 11<br>
                    Ploiești, Prahova<br>
                    Cod poștal 100352<br>
                    România
                </p>

            </div>


            {{-- Program --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    🕘
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    Program
                </h2>

                <p class="mt-2 text-slate-600 leading-7">
                    Luni - Vineri<br>
                    09:00 - 17:00
                </p>

                <p class="mt-3 text-sm text-slate-500">
                    În weekend și zilele libere legale nu este disponibil programul de lucru.
                </p>

            </div>

        </div>


        {{-- Mesaj final --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 text-center">

            <h2 class="text-2xl font-bold text-slate-900">
                Ai nevoie de ajutor?
            </h2>

            <p class="mt-4 text-slate-600 leading-7 max-w-2xl mx-auto">
                Pentru întrebări legate de produse, comenzi, plată sau livrare,
                ne poți contacta telefonic sau prin email.
            </p>

            <div class="mt-6 flex flex-col sm:flex-row justify-center gap-4">

                <a
                    href="tel:0750444672"
                    class="inline-flex items-center justify-center bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl px-6 py-3 font-semibold transition">
                    📞 Sună-ne
                </a>

                <a
                    href="mailto:novelionprime@gmail.com"
                    class="inline-flex items-center justify-center bg-slate-900 hover:bg-slate-800 text-white rounded-xl px-6 py-3 font-semibold transition">
                    ✉️ Trimite email
                </a>

            </div>

        </div>

    </div>

</div>

@endsection