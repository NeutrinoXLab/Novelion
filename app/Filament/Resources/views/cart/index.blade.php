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

                            <div class="flex gap-6 items-center">

                                {{-- Imagine --}}
                                <div class="w-28 h-28 rounded-2xl overflow-hidden bg-slate-100 flex-shrink-0">

                                    @if($item['product']->primary_image_path)

                                        <img
                                            src="{{ asset('storage/'.$item['product']->primary_image_path) }}"
                                            alt="{{ $item['product']->name }}"
                                            class="w-full h-full object-contain p-1">

                                    @else

                                        <div class="flex items-center justify-center h-full text-4xl">
                                            📦
                                        </div>

                                    @endif

                                </div>

                                {{-- Detalii produs --}}
                                <div class="flex-1">

                                    <h2 class="text-2xl font-bold text-slate-900">
                                        {{ $item['product']->name }}
                                    </h2>

                                    <p class="text-slate-500 mt-2">
                                        {{ number_format($item['price'], 2, ',', '.') }} Lei / buc.
                                    </p>

                                    <p class="text-sm text-slate-400 mt-1">
                                        Greutate:
                                        {{ number_format($item['product']->weight ?? 0, 2, ',', '.') }} kg / buc.
                                    </p>

                                    <form
                                        action="{{ route('cart.update', $item['product']) }}"
                                        method="POST"
                                        class="mt-5 flex items-center gap-3">

                                        @csrf

                                        <input
                                            type="number"
                                            name="quantity"
                                            min="1"
                                            max="{{ $item['product']->stock_quantity }}"
                                            value="{{ $item['quantity'] }}"
                                            class="w-24 rounded-xl border border-slate-300 text-center">

                                        <button
                                            type="submit"
                                            class="bg-cyan-500 text-white px-4 py-2 rounded-xl hover:bg-cyan-600">

                                            Actualizează

                                        </button>

                                    </form>

                                </div>

                                {{-- Total produs --}}
                                <div class="text-right">

                                    <div class="text-3xl font-bold text-cyan-600">
                                        {{ number_format($item['subtotal'], 2, ',', '.') }} Lei
                                    </div>

                                    <form
                                        action="{{ route('cart.remove', $item['product']) }}"
                                        method="POST"
                                        class="mt-5">

                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="text-red-500 hover:text-red-700 font-semibold">

                                            🗑 Elimină

                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>

                    @endforeach

                </div>


                {{-- REZUMAT COMANDĂ --}}
                <div>

                    <div class="bg-white rounded-3xl shadow-sm p-8 sticky top-8">

                        <h2 class="text-3xl font-bold mb-8">
                            Rezumat comandă
                        </h2>


                        {{-- Număr produse --}}
                        <div class="flex justify-between text-lg mb-4">

                            <span>
                                Produse
                            </span>

                            <span>
                                {{ $count }}
                            </span>

                        </div>


                        {{-- Subtotal --}}
                        <div class="flex justify-between text-lg mb-4">

                            <span>
                                Subtotal
                            </span>

                            <span class="font-semibold">
                                {{ number_format($subtotal, 2, ',', '.') }} Lei
                            </span>

                        </div>


                        {{-- Greutate totală --}}
                        <div class="flex justify-between text-sm text-slate-500 mb-3">

                            <span>
                                Greutate totală
                            </span>

                            <span>
                                {{ number_format($cart->totalWeight(), 2, ',', '.') }} kg
                            </span>

                        </div>


                        {{-- Volum total --}}
                        <div class="flex justify-between text-sm text-slate-500 mb-6">

                            <span>
                                Volum total
                            </span>

                            <span>
                                {{ number_format($cart->totalVolume(), 0, ',', '.') }} cm³
                            </span>

                        </div>


                        <hr>


                        {{-- LIVRARE --}}
                        <div class="flex justify-between text-lg mt-6 mb-2">

                            <span>
                                Livrare
                            </span>

                            <span class="font-semibold">

                                @if($shippingCost > 0)

                                    {{ number_format($shippingCost, 2, ',', '.') }} Lei

                                @else

                                    Gratuită

                                @endif

                            </span>

                        </div>


                        {{-- Categoria transport --}}
                        @if($shippingName)

                            <div class="text-sm text-slate-400 text-right mb-6">

                                Tarif transport:
                                <strong>{{ $shippingName }}</strong>

                            </div>

                        @else

                            <div class="text-sm text-red-500 text-right mb-6">

                                Nu există un tarif de transport disponibil
                                pentru această comandă.

                            </div>

                        @endif


                        <hr>


                        {{-- TOTAL --}}
                        <div class="flex justify-between items-center mt-8">

                            <span class="text-2xl font-bold">
                                Total
                            </span>

                            <span class="text-4xl font-bold text-cyan-600">

                                {{ number_format($total, 2, ',', '.') }} Lei

                            </span>

                        </div>


                        {{-- FINALIZARE --}}
                        <a
                            href="{{ route('checkout.index') }}"
                            class="block w-full mt-10 bg-cyan-500 hover:bg-cyan-600 text-white py-4 rounded-2xl text-xl font-bold text-center transition">

                            Finalizează comanda

                        </a>


                        {{-- Golește coșul --}}
                        <form
                            action="{{ route('cart.clear') }}"
                            method="POST">

                            @csrf

                            <button
                                type="submit"
                                class="w-full mt-4 border border-red-200 text-red-600 py-3 rounded-2xl hover:bg-red-50 transition">

                                Golește coșul

                            </button>

                        </form>

                    </div>

                </div>

            </div>

        @else

            {{-- COȘ GOL --}}
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
