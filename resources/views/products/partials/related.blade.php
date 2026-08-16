@if($relatedProducts->count())

<section class="py-20 bg-slate-50 border-t border-slate-200">

    <div class="max-w-7xl mx-auto px-6">

        <div class="flex items-center justify-between mb-10">

            <div>

                <h2 class="text-4xl font-bold text-slate-900">

                    Produse similare

                </h2>

                <p class="mt-2 text-slate-600">

                    Alte produse din aceeași categorie care te-ar putea interesa.

                </p>

            </div>

        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">

            @foreach($relatedProducts as $relatedProduct)

                <x-product-card :product="$relatedProduct" />

            @endforeach

        </div>

    </div>

</section>

@endif