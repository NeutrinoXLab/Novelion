<section class="bg-gradient-to-b from-slate-50 to-white">

    <div class="max-w-7xl mx-auto px-6 pt-6 pb-16">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

            {{-- Text --}}
            <div>

                <span
                    class="inline-block bg-cyan-100 text-cyan-700 px-4 py-2 rounded-full text-sm font-semibold mb-6">
                    ⭐ Calitate verificată
                </span>

                <h1 class="text-5xl lg:text-6xl font-extrabold leading-tight text-slate-900">
                    Produse atent selectate pentru
                    <span class="text-cyan-500">
                        casa și familia ta
                    </span>
                </h1>

                <p class="mt-6 text-lg text-slate-600 leading-8">
                    Descoperă produse de calitate, livrare rapidă și suport dedicat.
                    Tot ce ai nevoie într-un singur loc.
                </p>

                <div class="mt-10 flex flex-wrap gap-4">

                    <a
                        href="{{ route('products.index') }}"
                        class="bg-cyan-500 hover:bg-cyan-600 text-white px-8 py-4 rounded-xl font-semibold shadow-lg transition duration-300">
                        Descoperă produsele
                    </a>

                    <a
    href="{{ route('products.new') }}"
    class="border border-slate-300 hover:bg-slate-100 px-8 py-4 rounded-xl font-semibold transition duration-300">
    Vezi noutățile
</a>

                </div>

            </div>

            {{-- Imagine --}}
            <div>

                <div class="rounded-3xl shadow-2xl overflow-hidden bg-white">

                    <img
                        src="{{ asset('images/hero/hero.png') }}"
                        alt="Novelion Hero"
                        class="block w-full h-auto">

                </div>

            </div>

        </div>

    </div>

</section>