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

    {{-- NEWSLETTER --}}
    <x-newsletter />

@endsection