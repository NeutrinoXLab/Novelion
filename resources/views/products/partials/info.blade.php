@php
    $isFavorite = auth()->check()
        ? auth()->user()->wishlist->contains($product->id)
        : false;
@endphp

<div>

    <div class="flex items-start justify-between">

        <div class="flex flex-wrap gap-3">

            <span
                class="bg-cyan-100 text-cyan-700 px-4 py-2 rounded-full text-sm font-semibold">

                {{ $product->category->name }}

            </span>

            @if($product->is_new)

                <span
                    class="bg-green-100 text-green-700 px-4 py-2 rounded-full text-sm font-semibold">

                    🆕 Nou

                </span>

            @endif

            @if($product->is_featured)

                <span
                    class="bg-amber-100 text-amber-700 px-4 py-2 rounded-full text-sm font-semibold">

                    ⭐ Recomandat

                </span>

            @endif

            @if($product->sale_price && $product->referencePrice() > (float) $product->sale_price)

                <span
                    class="bg-red-100 text-red-700 px-4 py-2 rounded-full text-sm font-semibold">

                    🔥 Reducere

                </span>

            @endif

        </div>

        @auth

<form
    action="{{ route('wishlist.toggle', $product) }}"
    method="POST">

    @csrf

    <button
        type="submit"
        class="w-14 h-14 rounded-2xl border border-slate-200 bg-white hover:border-red-300 transition flex items-center justify-center ml-6 shrink-0">

        @if($isFavorite)

            <span class="text-3xl text-red-500">❤️</span>

        @else

            <span class="text-3xl text-slate-400 hover:text-red-500">🤍</span>

        @endif

    </button>

</form>

@else

<a
    href="{{ route('login') }}"
    class="w-14 h-14 rounded-2xl border border-slate-200 bg-white hover:border-red-300 transition flex items-center justify-center ml-6 shrink-0">

    <span class="text-3xl text-slate-400 hover:text-red-500">🤍</span>

</a>

@endauth

    </div>

    <h1 class="mt-6 text-5xl font-bold text-slate-900">

        {{ $product->name }}

    </h1>

    <div class="mt-5 flex items-center gap-3">

    <div class="flex text-amber-400 text-2xl">

        @for($i = 1; $i <= 5; $i++)

            @if($product->average_rating >= $i)

                ★

            @else

                ☆

            @endif

        @endfor

    </div>

    <span class="text-slate-700 font-semibold">

        {{ number_format($product->average_rating, 1) }}

    </span>

    <span class="text-slate-500">

        ({{ $product->reviews_count }} recenzii)

    </span>

</div>

    @if($product->brand)

        <p class="mt-5 text-slate-500">

            Brand:

            <span class="font-semibold">

                {{ $product->brand->name }}

            </span>

        </p>

    @endif

    <div class="mt-8">

        @if($product->sale_price && $product->referencePrice() > (float) $product->sale_price)

            @php

                $referencePrice = $product->referencePrice();

                $discount = round(
                    (($referencePrice-$product->sale_price)
                    /$referencePrice)*100
                );

                $saved =
                    $referencePrice-$product->sale_price;

            @endphp

            <div class="flex items-center gap-4">

                <div class="text-5xl font-bold text-red-600">

                    {{ number_format($product->sale_price,2,',','.') }} Lei

                </div>

                <span
                    class="bg-red-600 text-white px-4 py-2 rounded-xl font-bold">

                    -{{ $discount }}%

                </span>

            </div>

            <div class="mt-3 flex items-center gap-4">

                <span class="text-2xl text-slate-400 line-through">

                    {{ number_format($referencePrice,2,',','.') }} Lei

                </span>

                <span class="text-green-600 font-semibold">

                    Economisești
                    {{ number_format($saved,2,',','.') }} Lei

                </span>

            </div>

        @else

            <div class="text-5xl font-bold text-cyan-600">

                {{ number_format($product->sale_price ?: $product->selling_price,2,',','.') }} Lei

            </div>

        @endif

    </div>
        {{-- STOC --}}
    <div class="mt-8">

       @if($product->stock_quantity > 0)

    <div class="inline-flex items-center gap-2 bg-green-50 text-green-700 px-5 py-3 rounded-xl font-semibold">

        <span class="text-xl">✔</span>

        În stoc

    </div>

@else

            <div class="inline-flex items-center gap-2 bg-red-50 text-red-700 px-5 py-3 rounded-xl font-semibold">

                <span class="text-xl">✖</span>

                Stoc epuizat

            </div>

        @endif

    </div>

    {{-- CANTITATE + COȘ --}}
    <form
    action="{{ route('cart.add', $product) }}"
    method="POST"
    class="mt-10 flex gap-4">

    @csrf

    <input
        type="number"
        name="quantity"
        min="1"
        max="{{ $product->stock_quantity }}"
        value="1"
        @disabled($product->stock_quantity == 0)
        class="w-24 rounded-xl border border-slate-300 text-center text-lg font-semibold focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 disabled:bg-slate-100">

    <button
        type="submit"
        @disabled($product->stock_quantity == 0)
        class="flex-1 bg-cyan-500 hover:bg-cyan-600 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-xl py-4 text-lg font-semibold shadow-lg hover:shadow-xl transition">

        @if($product->stock_quantity > 0)
            🛒 Adaugă în coș
        @else
            Produs indisponibil
        @endif

    </button>

</form>

    {{-- BENEFICII --}}
    <div class="mt-10 grid gap-4">

        <div class="flex items-center gap-3">

            <span class="text-2xl">🚚</span>

            <span class="text-slate-700">

                Livrare numai în România; termenul curierului este comunicat separat

            </span>

        </div>

        <div class="flex items-center gap-3">

            <span class="text-2xl">↩️</span>

            <span class="text-slate-700">

                Retur în 14 zile

            </span>

        </div>

        <div class="flex items-center gap-3">

            <span class="text-2xl">🛡️</span>

            <span class="text-slate-700">

                Garanție legală de conformitate

            </span>

        </div>

    </div>

    @if($product->manufacturer_name || $product->model_identifier || $product->warnings || $product->safety_instructions)
    <div class="mt-12"><h2 class="text-2xl font-bold mb-4">Identificare și siguranță</h2>
        @if($product->manufacturer_name)<p><strong>Producător:</strong> {{ $product->manufacturer_name }}</p>@endif
        @if($product->manufacturer_contact)<p><strong>Contact producător:</strong> {{ $product->manufacturer_contact }}</p>@endif
        @if($product->model_identifier)<p><strong>Model/identificare:</strong> {{ $product->model_identifier }}</p>@endif
        @if($product->eu_responsible_person_name)<p><strong>Persoană responsabilă în UE:</strong> {{ $product->eu_responsible_person_name }} — {{ $product->eu_responsible_person_contact }}</p>@endif
        @if($product->warnings)<p><strong>Avertismente:</strong> {{ $product->warnings }}</p>@endif
        @if($product->safety_instructions)<p><strong>Instrucțiuni:</strong> {{ $product->safety_instructions }}</p>@endif
        @if($product->commercial_warranty)<p><strong>Garanție comercială a producătorului:</strong> {{ $product->commercial_warranty }}</p>@endif
    </div>
    @endif

    {{-- DESCRIERE --}}
    <div class="mt-12">

        <h2 class="text-2xl font-bold mb-4">

            Descriere

        </h2>

        <div class="prose prose-slate max-w-none leading-8">

            {!! nl2br(e($product->description)) !!}

        </div>

    </div>

</div>
