<div>

    <div class="bg-white rounded-3xl overflow-hidden shadow flex items-center justify-center">

        <template x-if="selectedImage">

            <img
                :src="selectedImage"
                alt="{{ $product->name }}"
                class="w-full h-[420px] sm:h-[520px] lg:h-[600px] object-contain transition duration-300">

        </template>

        <template x-if="!selectedImage">

            <div class="h-[600px] flex items-center justify-center text-8xl">

                📦

            </div>

        </template>

    </div>

    @if($product->images->count())

        <div class="grid grid-cols-5 gap-4 mt-6">

            @foreach($product->images as $image)

                <button
                    type="button"
                    @click="selectedImage='{{ asset('storage/'.$image->image_path) }}'"
                    :class="selectedImage === '{{ asset('storage/'.$image->image_path) }}'
                        ? 'ring-4 ring-cyan-500 border-cyan-500'
                        : 'border-slate-200'"
                    class="rounded-2xl overflow-hidden border bg-white transition hover:border-cyan-500">

                    <img
                        src="{{ asset('storage/'.$image->image_path) }}"
                        class="w-full h-20 sm:h-24 object-contain p-1">

                </button>

            @endforeach

        </div>

    @endif

</div>
