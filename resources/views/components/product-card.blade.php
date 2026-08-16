<div class="group relative flex flex-col h-full bg-white rounded-3xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">

    {{-- Link peste tot cardul --}}
    <a
        href="{{ route('products.show', $product) }}"
        class="absolute inset-0 z-10"
        aria-label="{{ $product->name }}">
    </a>

    {{-- Imagine --}}
    <div class="relative overflow-hidden bg-slate-100">

        @if($product->primary_image_path)

            <img
                src="{{ asset('storage/' . $product->primary_image_path) }}"
                alt="{{ $product->name }}"
                class="w-full h-52 object-cover group-hover:scale-105 transition duration-500">

        @else

            <div class="w-full h-52 flex items-center justify-center text-5xl">
                📦
            </div>

        @endif

        {{-- Badge NOU / REDUCERE --}}
        @if($product->sale_price)

            <span class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-2.5 py-1 rounded-full z-20">
                REDUCERE
            </span>

        @elseif($product->is_new)

            <span class="absolute top-3 left-3 bg-green-500 text-white text-xs font-bold px-2.5 py-1 rounded-full z-20">
                NOU
            </span>

        @endif

        {{-- Wishlist --}}
        @auth

            <form
                action="{{ route('wishlist.toggle', $product) }}"
                method="POST"
                class="absolute top-3 right-3 z-20">

                @csrf

                <button
                    type="submit"
                    class="w-9 h-9 rounded-full bg-white shadow hover:bg-red-50 transition">

                    @if(auth()->user()->wishlist->contains($product->id))
                        ❤️
                    @else
                        🤍
                    @endif

                </button>

            </form>

        @else

            <a
                href="{{ route('login') }}"
                class="absolute top-3 right-3 z-20 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center hover:bg-red-50 transition">

                🤍

            </a>

        @endauth

    </div>

    {{-- Content --}}
    <div class="p-4 relative z-20 flex flex-col flex-1">

        @if($product->brand)

            <p class="text-xs uppercase tracking-wider text-slate-500">

                {{ $product->brand->name }}

            </p>

        @endif

        <h3 class="mt-1 font-semibold text-base text-slate-900 line-clamp-2 min-h-[46px]">

            {{ $product->name }}

        </h3>

        <div class="mt-2 text-sm text-yellow-500">
            ★★★★★
        </div>

        {{-- Preț --}}
        <div class="mt-3 min-h-[64px] flex flex-col justify-end">

            @if($product->sale_price)

                <div class="text-sm text-gray-400 line-through">

                    {{ number_format($product->selling_price,2,',','.') }} Lei

                </div>

                <div class="text-2xl font-bold text-red-600">

                    {{ number_format($product->sale_price,2,',','.') }} Lei

                </div>

            @else

                <div class="h-5"></div>

                <div class="text-2xl font-bold text-cyan-600">

                    {{ number_format($product->selling_price,2,',','.') }} Lei

                </div>

            @endif

        </div>

        {{-- Stoc --}}
<div class="mt-2 min-h-[22px]">

    @if($product->stock_quantity <= 0)

        <span class="text-red-600 text-sm font-medium">

            ❌ Stoc epuizat

        </span>

    @elseif($product->stock_quantity <= $product->low_stock_threshold)

        <span class="text-orange-600 text-sm font-medium">

            ⚠️ Stoc limitat

        </span>

    @else

        <span class="text-green-600 text-sm font-medium">

            ✔ În stoc

        </span>

    @endif

        </div>

        {{-- Buton --}}
        <form
            action="{{ route('cart.add', $product) }}"
            method="POST"
            class="mt-auto pt-4 relative z-30">

            @csrf

            <input
                type="hidden"
                name="quantity"
                value="1">

            <button
                type="submit"
                class="w-full bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl py-2.5 font-semibold transition">

                🛒 Adaugă în coș

            </button>

        </form>

    </div>

</div>