<div
    x-data="{
        selectedImage: '{{ $product->primary_image_path
            ? asset('storage/' . $product->primary_image_path)
            : '' }}'
    }">

    {{-- Imagine principală --}}
    <div class="bg-slate-100 rounded-3xl overflow-hidden shadow">

        <template x-if="selectedImage">

            <img
                :src="selectedImage"
                alt="{{ $product->name }}"
                class="w-full h-[600px] object-cover transition duration-300">

        </template>

        <template x-if="!selectedImage">

            <div class="h-[600px] flex items-center justify-center text-8xl">

                📦

            </div>

        </template>

    </div>

    {{-- Miniaturi --}}
    @if($product->images->count())

        <div class="grid grid-cols-5 gap-4 mt-6">

            @foreach($product->images as $image)

                <button
                    type="button"
                    @click="selectedImage='{{ asset('storage/'.$image->image_path) }}'"
                    :class="selectedImage === '{{ asset('storage/'.$image->image_path) }}'
                        ? 'ring-4 ring-cyan-500 border-cyan-500'
                        : 'border-slate-200'"
                    class="rounded-2xl overflow-hidden border transition hover:border-cyan-500">

                    <img
                        src="{{ asset('storage/'.$image->image_path) }}"
                        alt="{{ $product->name }}"
                        class="w-full h-24 object-cover">

                </button>

            @endforeach

        </div>

    @endif

</div>