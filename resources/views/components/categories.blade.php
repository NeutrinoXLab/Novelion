@php
    $categories = \App\Models\Category::where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->take(6)
        ->get();
@endphp

<section class="py-16 bg-white">

    <div class="max-w-7xl mx-auto px-6">

        <div class="text-center mb-10">

            <h2 class="text-3xl font-bold text-slate-900">
                Categorii populare
            </h2>

            <p class="mt-3 text-slate-600">
                Descoperă cele mai populare categorii din magazin.
            </p>

        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">

            @forelse($categories as $category)

                <a
                    href="{{ route('categories.show', $category) }}"
                    class="group flex flex-col h-full bg-slate-50 hover:bg-cyan-50 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-300">

                    {{-- Imagine categorie --}}
                    <div class="aspect-square overflow-hidden bg-white flex items-center justify-center">

                        @if($category->image_path)

                            <img
                                src="{{ asset('storage/' . $category->image_path) }}"
                                alt="{{ $category->name }}"
                                class="w-full h-full object-contain p-3 group-hover:scale-105 transition duration-500">

                        @else

                            <div class="w-full h-full flex items-center justify-center text-5xl">
                                📦
                            </div>

                        @endif

                    </div>

                    {{-- Nume categorie --}}
                    <div class="p-4 text-center flex items-center justify-center min-h-[72px]">

                        <h3 class="font-semibold text-slate-800 group-hover:text-cyan-600 transition">
                            {{ $category->name }}
                        </h3>

                    </div>

                </a>

            @empty

                <div class="col-span-6 text-center text-gray-500">
                    Nu există categorii.
                </div>

            @endforelse

        </div>

    </div>

</section>