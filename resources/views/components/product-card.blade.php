<div
    class="group relative flex flex-col h-full
           bg-white
           rounded-3xl
           overflow-hidden
           border border-slate-200
           shadow-sm
           hover:shadow-xl
           hover:-translate-y-1
           transition-all duration-300"
>

    {{-- Link peste card --}}
    <a
        href="{{ route('products.show', $product) }}"
        class="absolute inset-0 z-10"
        aria-label="{{ $product->name }}"
    ></a>


    {{-- IMAGINE --}}
    <div
        style="
            position: relative;
            width: 100%;
            height: 230px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8fafc;
        "
    >

        @if($product->primary_image_path)

            <img
                src="{{ asset('storage/' . $product->primary_image_path) }}"
                alt="{{ $product->name }}"
                style="
                    max-width: 100%;
                    max-height: 100%;
                    width: auto;
                    height: auto;
                    object-fit: contain;
                    display: block;
                    padding: 12px;
                "
                class="group-hover:scale-105 transition duration-500"
            >

        @else

            <div class="text-5xl">
                📦
            </div>

        @endif


        {{-- BADGE REDUCERE / NOU --}}
        @if($product->sale_price && $product->referencePrice() > (float) $product->sale_price)

            <span
                style="
                    position: absolute;
                    top: 12px;
                    left: 12px;
                    z-index: 30;
                "
                class="bg-red-500 text-white
                       text-xs font-bold
                       px-2.5 py-1
                       rounded-full"
            >
                REDUCERE
            </span>

        @elseif($product->is_new)

            <span
                style="
                    position: absolute;
                    top: 12px;
                    left: 12px;
                    z-index: 30;
                "
                class="bg-green-500 text-white
                       text-xs font-bold
                       px-2.5 py-1
                       rounded-full"
            >
                NOU
            </span>

        @endif


        {{-- WISHLIST --}}
        @auth

            <form
                action="{{ route('wishlist.toggle', $product) }}"
                method="POST"
                style="
                    position: absolute;
                    top: 12px;
                    right: 12px;
                    z-index: 40;
                    margin: 0;
                "
            >

                @csrf

                <button
                    type="submit"
                    style="
                        width: 36px;
                        height: 36px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 0;
                    "
                    class="rounded-full bg-white shadow
                           hover:bg-red-50 transition"
                >

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
                style="
                    position: absolute;
                    top: 12px;
                    right: 12px;
                    z-index: 40;
                    width: 36px;
                    height: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                "
                class="rounded-full bg-white shadow
                       hover:bg-red-50 transition"
            >
                🤍
            </a>

        @endauth

    </div>


    {{-- CONTINUT CARD --}}
    <div class="p-4 relative z-20 flex flex-col flex-1">


        {{-- BRAND --}}
        <div
            style="
                height: 20px;
                min-height: 20px;
            "
        >

            @if($product->brand)

                <p class="text-xs uppercase tracking-wider text-slate-500">
                    {{ $product->brand->name }}
                </p>

            @endif

        </div>


        {{-- NUME PRODUS --}}
        <div
            style="
                height: 52px;
                min-height: 52px;
                display: flex;
                align-items: flex-start;
            "
        >

            <h3
                class="font-semibold text-base text-slate-900 line-clamp-2"
            >
                {{ $product->name }}
            </h3>

        </div>


        {{-- RATING --}}
        <div
            style="
                height: 24px;
                min-height: 24px;
            "
            class="text-sm text-yellow-500"
        >
            ★★★★★
        </div>


        {{-- PRET --}}
        <div
            style="
                height: 64px;
                min-height: 64px;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
            "
        >

            @if($product->sale_price && $product->referencePrice() > (float) $product->sale_price)

                <div class="text-sm text-gray-400 line-through">
                    {{ number_format($product->referencePrice(), 2, ',', '.') }} Lei
                </div>

                <div class="text-2xl font-bold text-red-600">
                    {{ number_format($product->sale_price, 2, ',', '.') }} Lei
                </div>

            @else

                <div class="h-5"></div>

                <div class="text-2xl font-bold text-cyan-600">
                    {{ number_format($product->sale_price ?: $product->selling_price, 2, ',', '.') }} Lei
                </div>

            @endif

        </div>


        {{-- STOC --}}
        <div
            style="
                height: 22px;
                min-height: 22px;
            "
            class="mt-2"
        >

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


        {{-- BUTON ADAUGĂ ÎN COȘ --}}
        <form
            action="{{ route('cart.add', $product) }}"
            method="POST"
            class="mt-auto pt-4 relative z-30"
        >

            @csrf

            <input
                type="hidden"
                name="quantity"
                value="1"
            >

            <button
                type="submit"
                class="w-full
                       bg-cyan-500
                       hover:bg-cyan-600
                       text-white
                       rounded-xl
                       py-2.5
                       font-semibold
                       transition"
            >
                🛒 Adaugă în coș
            </button>

        </form>

    </div>

</div>
