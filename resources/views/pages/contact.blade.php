@extends('layouts.app')

@section('title', 'Contact')

@section('content')
    <x-information-page eyebrow="Suntem aici să ajutăm" title="Contact Novelion">
        <div class="grid gap-8 md:grid-cols-2">
            <x-information-section title="Datele societății">
                <p><strong class="text-slate-900">NOVELION S.R.L.</strong></p>
                <p>CUI 52627291 · Nr. Registrul Comerțului J2025075714007</p>
                <p>Str. Daciei nr. 11, Ploiești, Prahova, 100352</p>
            </x-information-section>

            <x-information-section title="Relații cu clienții">
                <p>Email clienți: <a class="font-medium text-cyan-700 underline underline-offset-4 hover:text-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600" href="mailto:novelionprime@gmail.com">novelionprime@gmail.com</a></p>
                <p>Telefon: <a class="font-medium text-cyan-700 underline underline-offset-4 hover:text-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600" href="tel:+40750444672">0750 444 672</a></p>
                <p>Program: luni–vineri, 09:00–17:00</p>
            </x-information-section>
        </div>

        <x-information-section title="Retururi și reclamații" class="border-t border-slate-200 pt-8">
            <p>Aceasta este și adresa oficială pentru retururi și reclamații.</p>
        </x-information-section>
    </x-information-page>
@endsection
