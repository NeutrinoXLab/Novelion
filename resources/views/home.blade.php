@extends('layouts.app')

@section('title', 'Acasă')

@section('content')

<x-hero />

<x-featured-products :featured-products="$featuredProducts" />

<x-categories :categories="$categories" />

<x-new-products />

<x-benefits />

<x-newsletter />

@endsection