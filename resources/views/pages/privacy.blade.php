@extends('layouts.app')

@section('title', 'Politica de confidențialitate și cookies')

@section('content')
    <x-information-page eyebrow="Date personale" title="Politica de confidențialitate și cookies">
        <p class="leading-8 text-slate-600">Operator: NOVELION S.R.L., CUI 52627291, J2025075714007, Str. Daciei nr. 11, Ploiești, Prahova, 100352. Contact: novelionprime@gmail.com, 0750 444 672.</p>

        <x-information-section title="Date și scopuri">
            <ul class="list-disc space-y-2 pl-6 marker:text-cyan-600">
                <li>cont: identificare, autentificare și administrare;</li>
                <li>comenzi și livrare: date de contact, facturare, livrare, plată și produse;</li>
                <li>retururi, reclamații și rambursări: produsele, cantitățile, descrierea, fotografiile opționale, IBAN când restituirea se face bancar și istoricul soluționării;</li>
                <li>newsletter: adresa, dovada cererii și confirmării double opt-in, dezabonarea și date tehnice limitate folosite pentru demonstrarea consimțământului;</li>
                <li>chat: mesajele și datele necesare răspunsului și securității;</li>
                <li>recenzii: rating, comentariu și asocierea cu o achiziție verificată. Publicăm numele afișat, ratingul, comentariul, data și marcajul „Achiziție verificată”.</li>
            </ul>
            <p>Temeiurile sunt executarea contractului, obligațiile legale, interesul legitim privind securitatea și apărarea drepturilor și consimțământul pentru newsletter.</p>
        </x-information-section>

        <x-information-section title="Destinatari și păstrare">
            <p>Datele sunt comunicate numai furnizorilor folosiți efectiv pentru găzduire/IT, plăți Stripe, email și curier, autorităților când legea o cere și consultanților implicați legitim. Durata este limitată la scop și la termenele legale contabile, fiscale, de garanție și de apărare a drepturilor.</p>
        </x-information-section>

        <x-information-section title="Ștergerea contului">
            <p>Ștergerea elimină accesul și anonimizează contul. Comenzile, retururile, reclamațiile, rambursările și evidențele care trebuie păstrate legal sau pentru soluționare rămân disponibile în condiții de acces limitat.</p>
        </x-information-section>

        <x-information-section title="Cookies">
            <p>Aplicația folosește numai cookie-uri și stocări strict necesare pentru sesiune, autentificare, coș, protecție CSRF, securitate și funcționarea magazinului. Nu folosim mecanismul propriu TrackVisits și nu instalăm un sistem de analytics prin această politică.</p>
        </x-information-section>

        <x-information-section title="Drepturi">
            <p>Poți solicita acces, rectificare, ștergere în limitele legii, restricționare, portabilitate, opoziție sau retragerea consimțământului și poți depune plângere la ANSPDCP. Scrie la novelionprime@gmail.com.</p>
        </x-information-section>
    </x-information-page>
@endsection
