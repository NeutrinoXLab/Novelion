@extends('layouts.app')

@section('title', 'Retur - Novelion')

@section('content')

<div class="bg-slate-100 py-16">

    <div class="max-w-5xl mx-auto px-6">

        {{-- Titlu --}}
        <div class="text-center mb-12">

            <p class="text-cyan-600 font-semibold uppercase tracking-widest text-sm">
                Retur
            </p>

            <h1 class="mt-3 text-4xl md:text-5xl font-bold text-slate-900">
                Politica de retur
            </h1>

            <p class="mt-5 text-lg text-slate-600 max-w-3xl mx-auto leading-8">
                Ne dorim ca fiecare comandă NOVELION să fie o experiență plăcută.
                Dacă ai nevoie să returnezi un produs, îți prezentăm mai jos
                informațiile importante.
            </p>

        </div>


        {{-- Dreptul de retragere --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <div class="flex items-start gap-4">

                <div class="w-12 h-12 shrink-0 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    ↩️
                </div>

                <div>

                    <h2 class="text-2xl font-bold text-slate-900">
                        Dreptul de retragere
                    </h2>

                    <p class="mt-4 text-slate-600 leading-7">
                        În cazul comenzilor online, consumatorul beneficiază de dreptul
                        de retragere din contract, în condițiile și termenul prevăzute
                        de legislația aplicabilă.
                    </p>

                    <p class="mt-3 text-slate-600 leading-7">
                        Pentru exercitarea dreptului de retragere, te rugăm să ne
                        contactezi și să ne comunici intenția de returnare a produsului.
                    </p>

                </div>

            </div>

        </div>


        {{-- Produsul trebuie returnat --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    📦
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    Condiția produsului
                </h2>

                <p class="mt-3 text-slate-600 leading-7">
                    Produsul trebuie returnat în condițiile prevăzute de legislația
                    aplicabilă. Îți recomandăm să păstrezi ambalajul și accesoriile
                    produsului până când ești sigur că dorești să îl păstrezi.
                </p>

            </div>


            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">

                <div class="w-12 h-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-2xl">
                    🧾
                </div>

                <h2 class="mt-5 text-xl font-bold text-slate-900">
                    Dovada achiziției
                </h2>

                <p class="mt-3 text-slate-600 leading-7">
                    Pentru identificarea rapidă a comenzii, te rugăm să ai la
                    îndemână numărul comenzii sau datele folosite la plasarea acesteia.
                </p>

            </div>

        </div>


        {{-- Costuri retur --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                Costurile returnării
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                În cazul exercitării dreptului de retragere, costurile directe
                ale returnării produsului sunt suportate de consumator,
                în condițiile prevăzute de legislația aplicabilă.
            </p>

        </div>


        {{-- Rambursare --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                Rambursarea banilor
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                După primirea și verificarea produsului returnat, rambursarea
                sumelor achitate se va realiza conform legislației aplicabile
                și metodei de plată utilizate pentru comandă.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                În funcție de situație, pot exista condiții legale specifice
                privind momentul efectuării rambursării.
            </p>

        </div>


        {{-- Excepții --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-8">

            <h2 class="text-2xl font-bold text-slate-900">
                Situații în care dreptul de retragere poate avea excepții
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                Legislația prevede anumite situații în care dreptul de retragere
                nu se aplică sau se aplică în condiții speciale, în funcție de
                natura produsului și de circumstanțele livrării.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                În cazul în care un produs se află într-o astfel de categorie,
                informațiile relevante vor fi prezentate în condițiile aplicabile
                produsului sau comenzii.
            </p>

        </div>


        {{-- Contact --}}
        <div class="bg-cyan-50 rounded-3xl p-8 md:p-10 text-center">

            <h2 class="text-2xl font-bold text-slate-900">
                Vrei să faci un retur?
            </h2>

            <p class="mt-4 text-slate-600 leading-7 max-w-2xl mx-auto">
                Contactează-ne și vom reveni cu informațiile necesare
                pentru procesarea solicitării tale.
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