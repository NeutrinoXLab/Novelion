@extends('layouts.app')

@section('title', 'Contul meu')

@section('content')

<section class="bg-slate-50 min-h-screen py-12">

```
<div class="max-w-7xl mx-auto px-6">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-cyan-600 to-cyan-500 rounded-3xl text-white p-10 shadow-xl">

        <h1 class="text-4xl font-black">
            Bun venit, {{ $user->name }} 👋
        </h1>

        <p class="mt-3 text-cyan-100 text-lg">
            Bine ai revenit în contul tău Novelion.
        </p>

    </div>


    {{-- Statistici --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mt-10">

        <div class="bg-white rounded-3xl p-8 shadow border border-slate-200">

            <div class="text-4xl">❤️</div>

            <div class="mt-4 text-3xl font-black">
                {{ $user->wishlist()->count() }}
            </div>

            <div class="text-slate-500 mt-2">
                Produse favorite
            </div>

        </div>


        <div class="bg-white rounded-3xl p-8 shadow border border-slate-200">

            <div class="text-4xl">📦</div>

            <div class="mt-4 text-3xl font-black">
                {{ $ordersCount }}
            </div>

            <div class="text-slate-500 mt-2">
                Comenzi
            </div>

        </div>


        <div class="bg-white rounded-3xl p-8 shadow border border-slate-200">

            <div class="text-4xl">🛒</div>

            <div class="mt-4 text-3xl font-black">
                {{ session('cart') ? array_sum(array_column(session('cart'), 'quantity')) : 0 }}
            </div>

            <div class="text-slate-500 mt-2">
                Produse în coș
            </div>

        </div>


        <div class="bg-white rounded-3xl p-8 shadow border border-slate-200">

            <div class="text-4xl">💰</div>

            <div class="mt-4 text-3xl font-black">
                {{ number_format($totalSpent, 2, ',', '.') }} Lei
            </div>

            <div class="text-slate-500 mt-2">
                Total cheltuit
            </div>

        </div>

    </div>


    {{-- Acțiuni rapide --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-8 mt-12">


        {{-- Comenzi --}}
        <a
            href="{{ route('my-orders.index') }}"
            class="bg-white rounded-3xl shadow hover:shadow-xl hover:-translate-y-1 transition p-8 border border-slate-200">

            <div class="text-5xl mb-4">📦</div>

            <h2 class="text-2xl font-bold">
                Comenzile mele
            </h2>

            <p class="mt-3 text-slate-500">
                Vezi istoricul comenzilor.
            </p>

        </a>


        {{-- Favorite --}}
        <a
            href="{{ route('wishlist.index') }}"
            class="bg-white rounded-3xl shadow hover:shadow-xl hover:-translate-y-1 transition p-8 border border-slate-200">

            <div class="text-5xl mb-4">❤️</div>

            <h2 class="text-2xl font-bold">
                Favorite
            </h2>

            <p class="mt-3 text-slate-500">
                Produsele salvate.
            </p>

        </a>


        {{-- Profil --}}
        <a
            href="{{ route('profile.edit') }}"
            class="bg-white rounded-3xl shadow hover:shadow-xl hover:-translate-y-1 transition p-8 border border-slate-200">

            <div class="text-5xl mb-4">👤</div>

            <h2 class="text-2xl font-bold">
                Profil
            </h2>

            <p class="mt-3 text-slate-500">
                Date personale și parolă.
            </p>

        </a>


        {{-- Deconectare --}}
        <form
            method="POST"
            action="{{ route('logout') }}">

            @csrf

            <button
                type="submit"
                class="w-full h-full text-left bg-white rounded-3xl shadow hover:shadow-xl hover:-translate-y-1 transition p-8 border border-slate-200">

                <div class="text-5xl mb-4">🚪</div>

                <h2 class="text-2xl font-bold">
                    Deconectare
                </h2>

                <p class="mt-3 text-slate-500">
                    Ieși din cont.
                </p>

            </button>

        </form>

    </div>


    {{-- Ultimele comenzi --}}
    <div class="mt-16">

        <h2 class="text-3xl font-bold text-slate-900 mb-6">
            Ultimele comenzi
        </h2>


        @if($latestOrders->isEmpty())

            <div class="bg-white rounded-3xl border border-slate-200 p-10 text-center text-slate-500 shadow">

                Nu ai plasat încă nicio comandă.

            </div>

        @else

            <div class="bg-white rounded-3xl border border-slate-200 shadow overflow-hidden">

                <div class="overflow-x-auto">

                    <table class="w-full min-w-[700px]">

                        <thead class="bg-slate-100">

                            <tr>

                                <th class="text-left px-6 py-4">
                                    Comandă
                                </th>

                                <th class="text-left px-6 py-4">
                                    Data
                                </th>

                                <th class="text-left px-6 py-4">
                                    Total
                                </th>

                                <th class="text-left px-6 py-4">
                                    Status
                                </th>

                                <th class="text-right px-6 py-4">
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @foreach($latestOrders as $order)

                                @php

                                    $statuses = [

                                        'pending' => [
                                            'label' => 'În așteptare',
                                            'class' => 'bg-yellow-100 text-yellow-700',
                                        ],

                                        'processing' => [
                                            'label' => 'În procesare',
                                            'class' => 'bg-blue-100 text-blue-700',
                                        ],

                                        'shipped' => [
                                            'label' => 'Expediată',
                                            'class' => 'bg-purple-100 text-purple-700',
                                        ],

                                        'delivered' => [
                                            'label' => 'Livrată',
                                            'class' => 'bg-emerald-100 text-emerald-700',
                                        ],

                                        'cancelled' => [
                                            'label' => 'Anulată',
                                            'class' => 'bg-red-100 text-red-700',
                                        ],

                                    ];

                                    $currentStatus = $statuses[$order->status] ?? [
                                        'label' => $order->status,
                                        'class' => 'bg-gray-100 text-gray-700',
                                    ];

                                @endphp


                                <tr class="border-t border-slate-200">

                                    <td class="px-6 py-5 font-semibold">
                                        {{ $order->order_number }}
                                    </td>

                                    <td class="px-6 py-5">
                                        {{ $order->created_at->format('d.m.Y') }}
                                    </td>

                                    <td class="px-6 py-5 font-semibold">
                                        {{ number_format($order->total, 2, ',', '.') }} Lei
                                    </td>

                                    <td class="px-6 py-5">

                                        <span
                                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold {{ $currentStatus['class'] }}">

                                            {{ $currentStatus['label'] }}

                                        </span>

                                    </td>

                                    <td class="px-6 py-5 text-right">

                                        <a
                                            href="{{ route('my-orders.show', $order) }}"
                                            class="text-cyan-600 hover:underline font-semibold">

                                            Vezi →

                                        </a>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

        @endif

    </div>

</div>
```

</section>

@endsection
