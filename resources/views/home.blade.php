@extends('layouts.app')

@section('title', 'Novelion - Produse pentru casa și familia ta')

@section('content')

    {{-- HERO --}}
    <x-hero />

    {{-- CATEGORII --}}
    <x-categories />

    {{-- PRODUSE RECOMANDATE --}}
    <x-featured-products :featured-products="$featuredProducts" />

    {{-- BENEFICII --}}
    <x-benefits />

    {{-- NEWSLETTER --}}
    <x-newsletter />

@endsection