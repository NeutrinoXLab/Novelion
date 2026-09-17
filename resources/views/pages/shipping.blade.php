@extends('layouts.app')

@section('title', 'Livrare')

@section('content')
    <x-information-page eyebrow="Informații despre comenzi" title="Livrare">
        <x-information-section title="Livrare în România și tarif">
            <p>Novelion livrează momentan numai în România. Curierul planificat este Sameday. Tariful se afișează numai când există o regulă validă pentru greutatea și volumul produselor; dacă livrarea nu poate fi calculată, comanda nu poate fi finalizată.</p>
        </x-information-section>

        <x-information-section title="Pregătire și predare">
            <p>Comenzile vor fi pregătite și predate curierului în aceeași zi lucrătoare dacă sunt plasate înainte de ora reală de ridicare comunicată de curier. După acea limită, predarea va avea loc în următoarea zi lucrătoare. Ora exactă nu este publicată până la confirmarea procedurii contractuale.</p>
        </x-information-section>

        <x-information-section title="Transportul efectiv">
            <p>Timpul de pregătire și predare este distinct de timpul efectiv de transport al curierului.</p>
        </x-information-section>
    </x-information-page>
@endsection
