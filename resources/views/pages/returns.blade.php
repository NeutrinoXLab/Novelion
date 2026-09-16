@extends('layouts.app')
@section('title', 'Retururi, neconformitate și garanții')
@section('content')
<section class="max-w-4xl mx-auto px-6 py-16 prose prose-slate"><h1>Retururi, produse neconforme și garanții</h1>
<h2>Vreau să returnez un produs</h2><p>Acest flux reprezintă exercitarea dreptului legal de retragere din contract. Poți declara retragerea în 14 zile de la intrarea în posesia fizică a produsului, selectând produsul și cantitatea. Motivul este opțional. Declarația online este confirmată prin email.</p>
<p>Expediază produsul fără întârzieri nejustificate și în cel mult 14 zile de la comunicarea retragerii la NOVELION S.R.L., Str. Daciei nr. 11, Ploiești, Prahova, 100352. Pentru simpla răzgândire, clientul suportă costul direct al returnării. Putem amâna rambursarea până la primirea produsului sau a dovezii expedierii.</p>
<p>Rambursăm sumele aferente produselor returnate și, când legea o cere pentru retragerea integrală, costul livrării standard. Costurile suplimentare ale unei opțiuni mai scumpe nu se rambursează. Excepțiile legale, inclusiv produse personalizate sau anumite produse sigilate din motive de igienă după desigilare, sunt cele din art. 16 OUG 34/2014.</p>
<h2>Formular-model de retragere</h2><p>Către NOVELION S.R.L., Str. Daciei nr. 11, Ploiești, Prahova, 100352, novelionprime@gmail.com: Vă informez prin prezenta cu privire la retragerea mea din contractul referitor la următoarele produse: [produse]. Comandate la [data]/primite la [data]. Nume, adresă, data și semnătura (numai dacă formularul este transmis pe hârtie).</p>
<h2>Produs defect/neconform</h2><p>Folosește fluxul separat, selectează produsul și cantitatea afectată și descrie problema. Fotografiile sunt opționale. Costul returului din simpla răzgândire nu se aplică automat unei reclamații de neconformitate.</p>
<h2>Garanția legală și garanția comercială</h2><p>Garanția legală de conformitate se aplică potrivit OUG 140/2021. Remediile pot include repararea sau înlocuirea și, în condițiile legii, reducerea proporțională a prețului ori încetarea contractului. O garanție comercială a producătorului este afișată separat numai când există informații reale și nu limitează garanția legală.</p>
<p>Forma oficială a notificării armonizate privind garanția legală este publicată în <a href="https://eur-lex.europa.eu/eli/reg_impl/2025/1960/oj/ron">Regulamentul de punere în aplicare (UE) 2025/1960</a>, aplicabil din 27 septembrie 2026.</p>
@if(today('Europe/Bucharest')->greaterThanOrEqualTo(\Carbon\CarbonImmutable::create(2026, 9, 27, 0, 0, 0, 'Europe/Bucharest')))
<img src="{{ asset('images/legal-guarantee-notice-ro.svg') }}" alt="Notificarea armonizată oficială privind garanția legală de conformitate" class="w-full max-w-2xl mx-auto">
@endif
<p>Contact reclamații: novelionprime@gmail.com · 0750 444 672, luni–vineri 09:00–17:00.</p></section>
@endsection
