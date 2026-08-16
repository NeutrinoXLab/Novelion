@extends('layouts.app')

@section('title', 'Livrare - Novelion')

@section('content')

<div class="bg-slate-100 py-16">

    <div class="max-w-5xl mx-auto px-6">

        {{-- Titlu --}}
        <div class="text-center mb-12">

            <p class="text-cyan-600 font-semibold uppercase tracking-widest text-sm">
                Livrare
            </p>

            <h1 class="mt-3 text-4xl md:text-5xl font-bold text-slate-900">
                Livrare simplă și sigură
            </h1>

            <p class="mt-5 text-lg text-slate-600 max-w-3xl mx-auto leading-8">
                Ne dorim ca produsele comandate de la NOVELION să ajungă la tine
                în condiții bune și într-un timp cât mai scurt.
            </p>

        </div>


        {{-- Informații principale --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

            {{-- Procesarea comenzii --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    📦
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    Procesarea comenzii
                </h2>

                <p class="mt-3 text-slate-600 leading-7">
                    După confirmarea comenzii, aceasta este pregătită pentru expediere.
                    În cazul în care avem nevoie de informații suplimentare,
                    te vom contacta folosind datele furnizate la plasarea comenzii.
                </p>

            </div>


            {{-- Livrarea --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    🚚
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    Expedierea
                </h2>

                <p class="mt-3 text-slate-600 leading-7">
                    Comenzile sunt expediate la adresa indicată de client în timpul
                    procesului de comandă. Produsele sunt ambalate corespunzător
                    înainte de expediere.
                </p>

            </div>


            {{-- Adresa --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    📍
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    Adresa de livrare
                </h2>

                <p class="mt-3 text-slate-600 leading-7">
                    Te rugăm să verifici cu atenție adresa și datele de contact
                    înainte de finalizarea comenzii.
                </p>

            </div>


            {{-- Verificarea coletului --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    🔎
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    La primirea coletului
                </h2>

                <p class="mt-3 text-slate-600 leading-7">
                    Îți recomandăm să verifici integritatea ambalajului la primirea
                    coletului. Dacă observi deteriorări vizibile, este important
                    să le semnalezi conform procedurii curierului.
                </p>

            </div>

        </div>


        {{-- Cost livrare --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-8">

            <div class="flex items-start gap-4">

                <div class="w-12 h-12 shrink-0 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    💳
                </div>

                <div>

                    <h2 class="text-2xl font-bold text-slate-900">
                        Costul livrării
                    </h2>

                    <p class="mt-4 text-slate-600 leading-7">
                        Costul livrării este afișat în timpul procesului de comandă,
                        înainte de finalizarea acesteia.
                    </p>

                    <p class="mt-3 text-slate-600 leading-7">
                        Astfel, vei putea vedea costul total al comenzii înainte
                        de a confirma achiziția.
                    </p>

                </div>

            </div>

        </div>


        {{-- Întrebări --}}
        <div class="bg-cyan-50 rounded-3xl p-8 md:p-10 text-center">

            <h2 class="text-2xl font-bold text-slate-900">
                Ai întrebări despre livrare?
            </h2>

            <p class="mt-4 text-slate-600 leading-7 max-w-2xl mx-auto">
                Dacă ai nevoie de informații suplimentare despre o comandă
                sau despre livrare, ne poți contacta.
            </p>

            <a
                href="{{ route('pages.contact') }}"
                class="inline-flex items-center justify-center mt-6 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl px-6 py-3 font-semibold transition">
                📞 Contactează-ne
            </a>

        </div>

    </div>

</div>

@endsection