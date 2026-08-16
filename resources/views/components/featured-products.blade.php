<section class="py-20 bg-white">

    <div class="max-w-7xl mx-auto px-6">

        <div class="flex justify-between items-center mb-10">

            <div>

                <h2 class="text-4xl font-bold text-slate-900">
                    Produse recomandate
                </h2>

                <p class="mt-2 text-slate-600">
                    Cele mai noi produse adăugate în magazin.
                </p>

            </div>

            <a href="{{ route('products.index') }}"
               class="text-cyan-600 font-semibold hover:underline">
                Vezi toate →
            </a>

        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">

            @forelse($featuredProducts as $product)

                <x-product-card :product="$product" />

            @empty

                <div class="col-span-4 text-center py-16 text-gray-500">
                    Nu există produse.
                </div>

            @endforelse

        </div>

    </div>

</section>