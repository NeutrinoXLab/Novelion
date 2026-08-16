@extends('layouts.app')

@section('title', 'Comenzile mele')

@section('content')

<section class="bg-slate-100 py-16 min-h-screen">

    <div class="max-w-7xl mx-auto px-6">

        <h1 class="text-5xl font-bold text-slate-900 mb-12">
            Comenzile mele
        </h1>

        @if($orders->count())

            <div class="bg-white rounded-3xl shadow-sm overflow-hidden">

                <table class="w-full">

                    <thead class="bg-slate-50">

                        <tr>

                            <th class="text-left px-6 py-4">
                                Nr. comandă
                            </th>

                            <th class="text-left px-6 py-4">
                                Data
                            </th>

                            <th class="text-left px-6 py-4">
                                Status
                            </th>

                            <th class="text-right px-6 py-4">
                                Total
                            </th>

                            <th class="text-right px-6 py-4">
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @foreach($orders as $order)

                            <tr class="border-t">

                                <td class="px-6 py-5 font-semibold">
                                    {{ $order->order_number }}
                                </td>

                                <td class="px-6 py-5">
                                    {{ $order->created_at->format('d.m.Y H:i') }}
                                </td>

                                <td class="px-6 py-5">

                                    @php
    $status = [
        'new' => [
            'label' => 'Nouă',
            'class' => 'bg-blue-100 text-blue-700',
        ],
        'confirmed' => [
            'label' => 'Confirmată',
            'class' => 'bg-green-100 text-green-700',
        ],
        'processing' => [
            'label' => 'În pregătire',
            'class' => 'bg-yellow-100 text-yellow-700',
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

    $current = $status[$order->status] ?? [
        'label' => $order->status,
        'class' => 'bg-gray-100 text-gray-700',
    ];
@endphp

<span class="px-3 py-1 rounded-full text-sm font-semibold {{ $current['class'] }}">
    {{ $current['label'] }}
</span>

                                </td>

                                <td class="px-6 py-5 text-right font-bold">

                                    {{ number_format($order->total,2,',','.') }} Lei

                                </td>

                                <td class="px-6 py-5 text-right">

                                    <a
                                        href="{{ route('my-orders.show', $order) }}"
                                        class="text-cyan-600 hover:underline">

                                        Vezi

                                    </a>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

            <div class="mt-8">

                {{ $orders->links() }}

            </div>

        @else

            <div class="bg-white rounded-3xl shadow-sm p-20 text-center">

                <div class="text-7xl mb-6">

                    📦

                </div>

                <h2 class="text-3xl font-bold mb-4">

                    Nu ai încă nicio comandă.

                </h2>

                <a
                    href="{{ route('home') }}"
                    class="inline-block mt-6 bg-cyan-500 hover:bg-cyan-600 text-white px-8 py-4 rounded-2xl font-bold">

                    Începe cumpărăturile

                </a>

            </div>

        @endif

    </div>

</section>

@endsection