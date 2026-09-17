@extends('layouts.app')

@section('title', 'Termeni și condiții')

@section('content')
    <x-information-page eyebrow="Informații pentru clienți" title="Termeni și condiții">
        <p class="leading-8 text-slate-600">Comerciant: NOVELION S.R.L., CUI 52627291, J2025075714007, Str. Daciei nr. 11, Ploiești, Prahova, 100352, novelionprime@gmail.com, 0750 444 672, luni–vineri 09:00–17:00. Societatea este neplătitoare de TVA.</p>

        <x-information-section title="Contract, preț și plată">
            <p>Produsele, cantitățile, prețul final în lei și costul livrării sunt prezentate înainte de apăsarea butonului „Comandă cu obligație de plată”. Contractul se formează după transmiterea comenzii și confirmarea ei pe email. Plata se face ramburs sau cu cardul prin Stripe, potrivit opțiunii afișate.</p>
        </x-information-section>

        <x-information-section title="Livrare">
            <p>Livrăm numai în România. Curierul planificat este Sameday. Predarea către curier și transportul efectiv sunt etape distincte; nu garantăm un interval rigid înainte ca serviciul să fie confirmat. Dacă livrarea nu poate fi calculată, checkoutul este blocat.</p>
        </x-information-section>

        <x-information-section title="Retur, neconformitate și rambursare">
            <p>Politica detaliată, formularul-model, funcția online, costul direct al returului și excepțiile sunt disponibile în pagina Retururi. Produsele defecte/neconforme urmează un flux separat, potrivit OUG 140/2021. Pentru Stripe, rambursarea este urmărită până la confirmarea procesatorului; pentru ramburs, restituirea se face prin transfer bancar pe baza IBAN-ului și acordului clientului. Retururile parțiale sunt rambursate pentru produsele și cantitățile acceptate.</p>
        </x-information-section>

        <x-information-section title="Garanții și reclamații">
            <p>Garanția legală nu este înlocuită sau limitată de o eventuală garanție comercială a producătorului. Reclamațiile se transmit la novelionprime@gmail.com sau la adresa sediului.</p>
        </x-information-section>

        <x-information-section title="SAL">
            <p>Înainte de SAL, ne poți contacta pentru soluționare directă. Consumatorii pot folosi platforma electronică SAL a ANPC: <a class="font-medium text-cyan-700 underline underline-offset-4 hover:text-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600" href="https://reclamatiisal.anpc.ro" rel="nofollow noopener">reclamatiisal.anpc.ro</a>. Vechea platformă europeană SOL/ODR nu mai este indicată.</p>
        </x-information-section>
    </x-information-page>
@endsection
