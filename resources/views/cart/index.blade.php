@extends('layouts.app')

@section('title', 'Coșul meu')

@section('content')

<section class="bg-slate-100 py-16 min-h-screen">

    <div class="max-w-7xl mx-auto px-6">

        <h1 class="text-5xl font-bold text-slate-900 mb-12">
            Coșul meu
        </h1>

        @if(count($items))

            <div class="grid lg:grid-cols-3 gap-10">

                {{-- PRODUSE --}}
                <div class="lg:col-span-2 space-y-6">

                    @foreach($items as $item)

                        <div class="bg-white rounded-3xl shadow-sm p-6">

                            <div class="flex flex-col sm:flex-row gap-6 sm:items-center">

                                {{-- Imagine --}}
                                <div class="w-32 h-32 sm:w-28 sm:h-28 rounded-2xl overflow-hidden bg-slate-100 flex-shrink-0 mx-auto sm:mx-0">

                                    @if($item['product']->primary_image_path)

                                        <img
                                            src="{{ asset('storage/'.$item['product']->primary_image_path) }}"
                                            class="w-full h-full object-cover">

                                    @else

                                        <div class="flex items-center justify-center h-full text-4xl">
                                            📦
                                        </div>

                                    @endif

                                </div>

                                {{-- Detalii --}}
                                <div class="flex-1">

                                    <h2 class="text-2xl font-bold text-slate-900">
                                        {{ $item['product']->name }}
                                    </h2>

                                    <p class="text-slate-500 mt-2">
                                        {{ number_format($item['price'],2,',','.') }} Lei / buc.
                                    </p>

                                    <form
                                        action="{{ route('cart.update',$item['product']) }}"
                                        method="POST"
                                        class="mt-5 flex items-center gap-3">

                                        @csrf

                                        <input
                                            type="number"
                                            name="quantity"
                                            min="1"
                                            value="{{ $item['quantity'] }}"
                                            class="w-24 rounded-xl border border-slate-300 text-center">

                                        <button
                                            class="bg-cyan-500 text-white px-4 py-2 rounded-xl hover:bg-cyan-600">

                                            Actualizează

                                        </button>

                                    </form>

                                </div>

                                {{-- Total produs --}}
                                <div class="text-right">

                                    <div class="text-3xl font-bold text-cyan-600">
                                        {{ number_format($item['subtotal'],2,',','.') }} Lei
                                    </div>

                                    <form
                                        action="{{ route('cart.remove',$item['product']) }}"
                                        method="POST"
                                        class="mt-5">

                                        @csrf
                                        @method('DELETE')

                                        <button
                                            class="text-red-500 hover:text-red-700 font-semibold">

                                            🗑 Elimină

                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>

                    @endforeach

                </div>

                {{-- TOTAL --}}
                <div>

                    <div class="bg-white rounded-3xl shadow-sm p-8 sticky top-8">

                        <h2 class="text-3xl font-bold mb-8">
                            Rezumat comandă
                        </h2>

                        <div class="flex justify-between text-lg mb-4">

                            <span>Produse</span>

                            <span>{{ $count }}</span>

                        </div>

                        <div class="flex justify-between text-lg mb-4">

    <span>Livrare</span>

    <span class="font-semibold">
        @if($shippingCost > 0)
            {{ number_format($shippingCost, 2, ',', '.') }} Lei
        @else
            Gratuită
        @endif
    </span>

</div>

                        <hr>

                        <div class="flex justify-between mt-8">

                            <span class="text-2xl font-bold">
                                Total
                            </span>

                            <span class="text-4xl font-bold text-cyan-600">
                                {{ number_format($total,2,',','.') }} Lei
                            </span>

                        </div>

                        <a
                            href="{{ route('checkout.index') }}"
                            class="block w-full mt-10 bg-cyan-500 hover:bg-cyan-600 text-white py-4 rounded-2xl text-xl font-bold text-center transition">

                            Finalizează comanda

                        </a>

                        <form
                            action="{{ route('cart.clear') }}"
                            method="POST">

                            @csrf

                            <button
                                class="w-full mt-4 border border-red-200 text-red-600 py-3 rounded-2xl hover:bg-red-50 transition">

                                Golește coșul

                            </button>

                        </form>

                    </div>

                </div>

            </div>

        @else

            <div class="bg-white rounded-3xl shadow-sm p-20 text-center">

                <div class="text-7xl mb-6">
                    🛒
                </div>

                <h2 class="text-4xl font-bold mb-4">
                    Coșul este gol
                </h2>

                <p class="text-slate-500 mb-10">
                    Nu ai adăugat încă niciun produs.
                </p>

                <a
                    href="{{ route('home') }}"
                    class="inline-block bg-cyan-500 hover:bg-cyan-600 text-white px-8 py-4 rounded-2xl font-bold transition">

                    Continuă cumpărăturile

                </a>

            </div>

        @endif

    </div>

</section>

@endsection