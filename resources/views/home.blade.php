@extends('layouts.app')

@section('title', 'Novelion - Produse pentru casa și familia ta')

@section('content')

    {{-- HERO --}}
    <x-hero />

    {{-- PRODUSE RECOMANDATE --}}
    <x-featured-products :featured-products="$featuredProducts" />

    {{-- CATEGORII --}}
    <x-categories />

    {{-- BENEFICII --}}
    <x-benefits />

    @if(today('Europe/Bucharest')->greaterThanOrEqualTo(\Carbon\CarbonImmutable::create(2026, 9, 27, 0, 0, 0, 'Europe/Bucharest')))
        <x-legal-guarantee-notice />
    @endif

    {{-- NEWSLETTER --}}
    <x-newsletter />

@endsection
