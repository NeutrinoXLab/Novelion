@php
    $categories = \App\Models\Category::where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->take(6)
        ->get();
@endphp

<section class="py-16 bg-white">

    <div class="max-w-7xl mx-auto px-6">

        {{-- TITLU --}}
        <div class="text-center mb-10">

            <h2 class="text-3xl font-bold text-slate-900">
                Categorii populare
            </h2>

            <p class="mt-3 text-slate-600">
                Descoperă cele mai populare categorii din magazin.
            </p>

        </div>


        {{-- CATEGORII --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">

            @forelse($categories as $category)

                <a
                    href="{{ route('categories.show', $category) }}"
                    class="group flex flex-col bg-slate-50 hover:bg-cyan-50 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-300"
                >

                    {{-- ZONA FIXĂ A IMAGINII --}}
                    <div
                        class="h-[230px] w-full flex items-center justify-center bg-slate-100 overflow-hidden shrink-0 p-3"
                    >

                        @if($category->image_path)

                            <img
                                src="{{ asset('storage/' . $category->image_path) }}"
                                alt="{{ $category->name }}"
                                class="max-w-full max-h-full w-auto h-auto object-contain group-hover:scale-105 transition duration-500"
                            >

                        @else

                            <div class="text-5xl">
                                📦
                            </div>

                        @endif

                    </div>


                    {{-- ZONA FIXĂ A NUMELUI --}}
                    <div
                        class="h-[64px] flex items-center justify-center px-3 text-center bg-white shrink-0"
                    >

                        <h3 class="font-semibold text-slate-800 group-hover:text-cyan-600 transition leading-tight">
                            {{ $category->name }}
                        </h3>

                    </div>

                </a>

            @empty

                <div class="col-span-6 text-center text-gray-500 py-10">
                    Nu există categorii.
                </div>

            @endforelse

        </div>

    </div>

</section>