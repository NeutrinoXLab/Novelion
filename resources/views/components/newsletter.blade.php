<section class="py-20 bg-cyan-600 text-white">

<div class="max-w-4xl mx-auto px-6 text-center">

    <h2 class="text-4xl font-bold">
        Abonează-te la Newsletter
    </h2>

    <p class="mt-4 text-cyan-100">
        Fii primul care află despre produse noi, promoții și oferte exclusive.
    </p>
    <p class="mt-2 text-sm text-cyan-100">Abonarea devine activă numai după confirmarea prin linkul primit pe email. Te poți dezabona oricând.</p>


    {{-- Mesaj de succes --}}
    @if(session('newsletter_success'))

        <div class="mt-6 bg-white/15 border border-white/30 rounded-2xl px-6 py-4 text-white">
            {{ session('newsletter_success') }}
        </div>

    @endif


    {{-- Formular --}}
    <form
        action="{{ route('newsletter.subscribe') }}"
        method="POST"
        class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">

        @csrf

        <div class="flex-1 max-w-lg">

            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="Adresa ta de email"
                autocomplete="email"
                required
                class="w-full rounded-xl px-5 py-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-cyan-300">

            @error('email')

                <p class="mt-2 text-left text-sm text-white">
                    {{ $message }}
                </p>

            @enderror

        </div>


        <button
            type="submit"
            class="bg-slate-900 hover:bg-black px-8 py-4 rounded-xl font-semibold transition">

            Abonează-mă

        </button>

    </form>

</div>

</section>
