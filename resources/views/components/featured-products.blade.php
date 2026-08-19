<section class="py-20 bg-white">

    <div class="max-w-7xl mx-auto px-6">

        {{-- Header secțiune --}}
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-10">

            <div>

                <h2 class="text-4xl font-bold text-slate-900">
                    Produse recomandate
                </h2>

                <p class="mt-2 text-slate-600">
                    Cele mai noi produse adăugate în magazin.
                </p>

            </div>

            <a
                href="{{ route('products.index') }}"
                class="shrink-0 text-cyan-600 font-semibold hover:text-cyan-700 hover:underline transition"
            >
                Vezi toate →
            </a>

        </div>


        {{-- Produse --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 items-stretch">

            @forelse($featuredProducts as $product)

                <div class="h-full">

                    <x-product-card :product="$product" />

                </div>

            @empty

                <div class="col-span-full text-center py-16 text-gray-500">

                    Nu există produse.

                </div>

            @endforelse

        </div>

    </div>

</section>