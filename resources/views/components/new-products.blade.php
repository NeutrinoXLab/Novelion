<section id="new-products" class="py-20 bg-slate-50">

    <div class="max-w-7xl mx-auto px-6">

        {{-- Titlu secțiune --}}
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-10">

            <div>
                <span class="text-sm font-semibold uppercase tracking-wider text-cyan-600">
                    Noutăți
                </span>

                <h2 class="mt-2 text-3xl lg:text-4xl font-bold text-slate-900">
                    Produse noi
                </h2>

                <p class="mt-3 text-slate-600">
                    Descoperă cele mai recente produse adăugate în magazin.
                </p>
            </div>

            <a
                href="{{ route('products.index') }}"
                class="shrink-0 text-cyan-600 font-semibold hover:text-cyan-700 transition">
                Vezi toate produsele →
            </a>

        </div>


        {{-- Produse --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">

            @forelse($latestProducts as $product)

                <x-product-card :product="$product" />

            @empty

                <div class="col-span-full text-center py-16">

                    <div class="text-5xl mb-4">
                        📦
                    </div>

                    <p class="text-slate-500">
                        Nu există produse noi momentan.
                    </p>

                </div>

            @endforelse

        </div>

    </div>

</section>