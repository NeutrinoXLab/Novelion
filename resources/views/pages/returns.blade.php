@extends('layouts.app')

@section('title', 'Retururi, neconformitate și garanții')

@section('content')
    <x-information-page eyebrow="Informații pentru clienți" title="Retururi, produse neconforme și garanții">
        <x-information-section title="Vreau să returnez un produs">
            <p>Acest flux reprezintă exercitarea dreptului legal de retragere din contract. Poți declara retragerea în 14 zile de la intrarea în posesia fizică a produsului, selectând produsul și cantitatea. Motivul este opțional. Declarația online este confirmată prin email.</p>
            <p>Expediază produsul fără întârzieri nejustificate și în cel mult 14 zile de la comunicarea retragerii la NOVELION S.R.L., Str. Daciei nr. 11, Ploiești, Prahova, 100352. Pentru simpla răzgândire, clientul suportă costul direct al returnării. Putem amâna rambursarea până la primirea produsului sau a dovezii expedierii.</p>
            <p>Rambursăm sumele aferente produselor returnate și, când legea o cere pentru retragerea integrală, costul livrării standard. Costurile suplimentare ale unei opțiuni mai scumpe nu se rambursează. Excepțiile legale, inclusiv produse personalizate sau anumite produse sigilate din motive de igienă după desigilare, sunt cele din art. 16 OUG 34/2014.</p>
        </x-information-section>

        <x-information-section title="Formular-model de retragere">
            <p>Către NOVELION S.R.L., Str. Daciei nr. 11, Ploiești, Prahova, 100352, novelionprime@gmail.com: Vă informez prin prezenta cu privire la retragerea mea din contractul referitor la următoarele produse: [produse]. Comandate la [data]/primite la [data]. Nume, adresă, data și semnătura (numai dacă formularul este transmis pe hârtie).</p>
        </x-information-section>

        <x-information-section title="Produs defect/neconform">
            <p>Folosește fluxul separat, selectează produsul și cantitatea afectată și descrie problema. Fotografiile sunt opționale. Costul returului din simpla răzgândire nu se aplică automat unei reclamații de neconformitate.</p>
        </x-information-section>

        <x-information-section title="Garanția legală și garanția comercială">
            <p>Garanția legală de conformitate se aplică potrivit OUG 140/2021. Remediile pot include repararea sau înlocuirea și, în condițiile legii, reducerea proporțională a prețului ori încetarea contractului. O garanție comercială a producătorului este afișată separat numai când există informații reale și nu limitează garanția legală.</p>
            <p>Forma oficială a notificării armonizate privind garanția legală este publicată în <a class="font-medium text-cyan-700 underline underline-offset-4 hover:text-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600" href="https://eur-lex.europa.eu/eli/reg_impl/2025/1960/oj/ron">Regulamentul de punere în aplicare (UE) 2025/1960</a>, aplicabil din 27 septembrie 2026.</p>
            @if(today('Europe/Bucharest')->greaterThanOrEqualTo(\Carbon\CarbonImmutable::create(2026, 9, 27, 0, 0, 0, 'Europe/Bucharest')))
                <img src="{{ asset('images/legal-guarantee-notice-ro.svg') }}" alt="Notificarea armonizată oficială privind garanția legală de conformitate" class="mx-auto w-full max-w-2xl">
            @endif
        </x-information-section>

        <x-information-section title="Contact pentru reclamații">
            <p>Contact reclamații: <a class="font-medium text-cyan-700 underline underline-offset-4 hover:text-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600" href="mailto:novelionprime@gmail.com">novelionprime@gmail.com</a> · <a class="font-medium text-cyan-700 underline underline-offset-4 hover:text-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600" href="tel:+40750444672">0750 444 672</a>, luni–vineri 09:00–17:00.</p>
        </x-information-section>
    </x-information-page>
@endsection
