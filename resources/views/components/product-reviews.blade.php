<section class="mt-16">

    <h2 class="text-3xl font-bold mb-8">
        Recenzii
    </h2>

    @auth

        @if(auth()->user()->orders()->whereIn('status', ['paid','processing','shipped','delivered'])->whereHas('items', fn($q) => $q->where('product_id', $product->id))->exists())
        <div class="bg-white rounded-3xl shadow border border-slate-200 p-8 mb-10">

            <h3 class="text-xl font-bold mb-6">
                Lasă o recenzie
            </h3>

            <form
                action="{{ route('reviews.store', $product) }}"
                method="POST">

                @csrf

                <div class="mb-6">

                    <label class="block mb-2 font-semibold">
                        Rating
                    </label>

                    <select
                        name="rating"
                        class="w-full rounded-xl border-slate-300">

                        @for($i=5;$i>=1;$i--)

                            <option value="{{ $i }}">
                                {{ $i }} ⭐
                            </option>

                        @endfor

                    </select>

                </div>

                <div class="mb-6">

                    <label class="block mb-2 font-semibold">
                        Comentariu
                    </label>

                    <textarea
                        name="comment"
                        rows="5"
                        required
                        class="w-full rounded-xl border-slate-300"></textarea>

                </div>

                <button
                    class="bg-cyan-500 hover:bg-cyan-600 text-white px-8 py-3 rounded-xl font-semibold">

                    Trimite recenzia

                </button>

            </form>

        </div>
        @else
        <div class="bg-slate-100 rounded-2xl p-6 mb-10">Poți scrie o recenzie după ce ai cumpărat acest produs de la Novelion.</div>
        @endif

    @else

        <div class="bg-slate-100 rounded-2xl p-6 mb-10">

            Pentru a scrie o recenzie trebuie să fii autentificat.

        </div>

    @endauth

    <div class="space-y-6">

        @forelse($product->reviews as $review)

            <div class="bg-white rounded-3xl shadow border border-slate-200 p-8">

                <div class="flex justify-between">

                    <div>

                        <div class="font-bold text-lg">

                            {{ $review->user->name }}

                        </div>

                        <div class="text-amber-500 text-xl">

                            {{ str_repeat('★',$review->rating) }}

                        </div>
                        @if($review->is_verified_purchase)<div class="text-xs font-semibold text-green-700 mt-1">Achiziție verificată</div>@endif

                    </div>

                    <div class="text-slate-400">

                        {{ $review->created_at->format('d.m.Y') }}

                    </div>

                </div>

                <p class="mt-6 text-slate-700 leading-7">

                    {{ $review->comment }}

                </p>

            </div>

        @empty

            <div class="text-slate-500">

                Acest produs nu are încă nicio recenzie.

            </div>

        @endforelse

    </div>

</section>
