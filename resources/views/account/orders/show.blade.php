@extends('layouts.app')

@section('title', 'Detalii comandă')

@section('content')

<section class="bg-slate-100 py-16 min-h-screen">

    <div class="max-w-6xl mx-auto px-6">

        <div class="flex justify-between items-center mb-10">

            <div>

                <h1 class="text-5xl font-bold">

                    Comanda {{ $order->order_number }}

                </h1>

                <p class="text-slate-500 mt-2">

                    Plasată la {{ $order->created_at->format('d.m.Y H:i') }}

                </p>

            </div>

            <a
                href="{{ route('my-orders.index') }}"
                class="bg-white border px-6 py-3 rounded-xl hover:bg-slate-50">

                ← Înapoi

            </a>

        </div>

        <div class="grid lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2">

                <div class="bg-white rounded-3xl shadow-sm p-8">

                    <h2 class="text-2xl font-bold mb-8">

                        Produse comandate

                    </h2>

                    <div class="space-y-6">

                        @foreach($order->items as $item)

                            <div class="flex justify-between border-b pb-5">

                                <div>

                                    <div class="font-semibold text-lg">

                                        {{ $item->product_name }}

                                    </div>

                                    <div class="text-slate-500">

                                        Cantitate:
                                        {{ $item->quantity }}

                                    </div>

                                </div>

                                <div class="font-bold text-cyan-600">

                                    {{ number_format($item->total,2,',','.') }} Lei

                                </div>

                            </div>

                        @endforeach

                    </div>

                </div>

            </div>

            <div>

                <div class="bg-white rounded-3xl shadow-sm p-8">

                    <h2 class="text-2xl font-bold mb-6">

                        Rezumat

                    </h2>

                    <div class="space-y-4">

                        <div class="flex justify-between">

                            <span>Status</span>

                            <span class="font-semibold">

                                {{ ucfirst($order->status) }}

                            </span>

                        </div>

                        <div class="flex justify-between">

                            <span>Plată</span>

                            <span>

                                {{ ucfirst($order->payment_status) }}

                            </span>

                        </div>

                        <div class="flex justify-between">

                            <span>Livrare</span>

                            <span>

                                {{ number_format($order->shipping_cost,2,',','.') }} Lei

                            </span>

                        </div>

                        <hr>

                        <div class="flex justify-between text-2xl font-bold">

                            <span>Total</span>

                            <span class="text-cyan-600">

                                {{ number_format($order->total,2,',','.') }} Lei

                            </span>

                        </div>

                    </div>

                </div>

                <div class="bg-white rounded-3xl shadow-sm p-8 mt-8">

                    <h2 class="text-2xl font-bold mb-6">

                        Adresa de livrare

                    </h2>

                    <p>

                        {{ $order->first_name }}
                        {{ $order->last_name }}

                    </p>

                    <p>

                        {{ $order->address }}

                    </p>

                    <p>

                        {{ $order->city }},
                        {{ $order->county }}

                    </p>

                    @if($order->postal_code)

                        <p>

                            {{ $order->postal_code }}

                        </p>

                    @endif

                    <p class="mt-4">

                        {{ $order->phone }}

                    </p>

                    <p>

                        {{ $order->email }}

                    </p>

                </div>

            </div>

        </div>

    </div>

</section>

@endsection