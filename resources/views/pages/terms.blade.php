@extends('layouts.app')

@section('title', 'Termeni și condiții - Novelion')

@section('content')

<div class="bg-slate-100 py-16">

    <div class="max-w-5xl mx-auto px-6">

        {{-- Titlu --}}
        <div class="text-center mb-12">

            <p class="text-cyan-600 font-semibold uppercase tracking-widest text-sm">
                Informații juridice
            </p>

            <h1 class="mt-3 text-4xl md:text-5xl font-bold text-slate-900">
                Termeni și condiții
            </h1>

            <p class="mt-5 text-lg text-slate-600 max-w-3xl mx-auto leading-8">
                Te rugăm să citești cu atenție termenii și condițiile
                aplicabile utilizării magazinului online NOVELION.
            </p>

        </div>


        {{-- 1. Informații generale --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                1. Informații generale
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                Magazinul online NOVELION este operat de NOVELION S.R.L.
                Prin utilizarea acestui site și prin plasarea unei comenzi,
                clientul acceptă termenii și condițiile prezentate în această pagină,
                în măsura în care acestea sunt aplicabile.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                Informațiile referitoare la identificarea comerciantului,
                datele de contact și celelalte informații obligatorii sunt
                puse la dispoziția clienților pe site.
            </p>

        </div>


        {{-- 2. Produsele --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                2. Produsele și informațiile afișate
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                NOVELION depune eforturi pentru ca informațiile referitoare la
                produse, imagini, caracteristici, disponibilitate și prețuri
                să fie cât mai corecte și actualizate.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                Imaginile produselor au caracter informativ și pot exista
                diferențe minore între imaginea afișată pe site și produsul
                livrat, în funcție de caracteristicile produsului și de
                prezentarea furnizorului.
            </p>

        </div>


        {{-- 3. Prețuri --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                3. Prețuri
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                Prețurile afișate pe site sunt exprimate în Lei și includ
                sau nu taxele aplicabile în funcție de regimul fiscal al
                comerciantului, conform informațiilor prezentate în cadrul
                procesului de comandă.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                Costurile de livrare, dacă sunt aplicabile, sunt afișate
                înainte de finalizarea comenzii.
            </p>

        </div>


        {{-- 4. Comanda --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                4. Plasarea și confirmarea comenzii
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                Clientul poate plasa o comandă prin intermediul funcționalităților
                disponibile pe site.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                După transmiterea comenzii, clientul va primi informațiile
                necesare referitoare la aceasta, conform datelor de contact
                furnizate.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                În situații excepționale, cum ar fi erori evidente de preț,
                indisponibilitatea produsului sau imposibilitatea obiectivă
                de executare a comenzii, NOVELION va informa clientul și va
                proceda conform legislației aplicabile.
            </p>

        </div>


        {{-- 5. Plata --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                5. Plata
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                Plata produselor se poate realiza prin metodele de plată
                disponibile în momentul plasării comenzii.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                În cazul plăților online, procesarea datelor de plată se
                realizează prin intermediul procesatorului de plăți utilizat
                de magazin, conform condițiilor și politicilor acestuia.
            </p>

        </div>


        {{-- 6. Livrarea --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                6. Livrarea
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                Produsele comandate sunt livrate la adresa indicată de client
                în timpul procesului de comandă.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                Informațiile referitoare la livrare, costurile acesteia și,
                după caz, termenul estimat sunt prezentate clientului înainte
                de finalizarea comenzii.
            </p>

            <a
                href="{{ route('pages.shipping') }}"
                class="inline-flex items-center mt-5 text-cyan-600 font-semibold hover:text-cyan-700 transition">
                Vezi informațiile despre livrare →
            </a>

        </div>


        {{-- 7. Retur --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                7. Dreptul de retragere și returul produselor
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                Consumatorii beneficiază de drepturile prevăzute de legislația
                aplicabilă privind retragerea din contract și returnarea produselor.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                Condițiile și informațiile referitoare la retur sunt prezentate
                în pagina dedicată politicii de retur.
            </p>

            <a
                href="{{ route('pages.returns') }}"
                class="inline-flex items-center mt-5 text-cyan-600 font-semibold hover:text-cyan-700 transition">
                Vezi politica de retur →
            </a>

        </div>


        {{-- 8. Contul de client --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                8. Contul de client
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                În cazul în care clientul își creează un cont pe NOVELION,
                acesta este responsabil pentru păstrarea confidențialității
                datelor de autentificare și pentru activitatea desfășurată
                prin intermediul contului său.
            </p>

        </div>


        {{-- 9. Proprietate intelectuală --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                9. Proprietate intelectuală
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                Conținutul site-ului NOVELION, inclusiv elementele grafice,
                textele, structura și elementele de design, este protejat
                conform legislației aplicabile privind proprietatea intelectuală.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                Reproducerea sau utilizarea conținutului site-ului în alte
                scopuri decât cele permise de lege este interzisă fără
                acordul titularului drepturilor.
            </p>

        </div>


        {{-- 10. Limitarea răspunderii --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                10. Disponibilitatea site-ului
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                NOVELION depune eforturi pentru menținerea funcționării
                corespunzătoare a site-ului, însă pot exista perioade în care
                anumite funcționalități sunt indisponibile din motive tehnice,
                de mentenanță sau din alte cauze independente de voința
                comerciantului.
            </p>

        </div>


        {{-- 11. Modificarea termenilor --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10 mb-6">

            <h2 class="text-2xl font-bold text-slate-900">
                11. Modificarea termenilor și condițiilor
            </h2>

            <p class="mt-4 text-slate-600 leading-7">
                NOVELION poate actualiza termenii și condițiile atunci când
                acest lucru este necesar, inclusiv ca urmare a modificărilor
                legislative sau a schimbărilor aduse serviciilor oferite.
            </p>

            <p class="mt-3 text-slate-600 leading-7">
                Versiunea actuală a termenilor și condițiilor va fi disponibilă
                pe această pagină.
            </p>

        </div>


        {{-- 12. Contact --}}
        <div class="bg-cyan-50 rounded-3xl p-8 md:p-10 text-center">

            <h2 class="text-2xl font-bold text-slate-900">
                Ai întrebări?
            </h2>

            <p class="mt-4 text-slate-600 leading-7 max-w-2xl mx-auto">
                Dacă ai nevoie de informații suplimentare referitoare la
                termenii și condițiile NOVELION, ne poți contacta.
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