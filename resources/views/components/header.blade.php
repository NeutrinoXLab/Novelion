<header class="bg-white shadow-sm border-b border-slate-200 sticky top-0 z-50">

    <div class="max-w-7xl mx-auto px-4 sm:px-6">

        {{-- DESKTOP --}}
        <div class="hidden lg:grid lg:grid-cols-[auto_minmax(300px,1fr)_auto_auto] lg:items-center lg:gap-6 lg:min-h-20">

            {{-- Logo --}}
            <a
                href="{{ route('home') }}"
                class="flex items-center shrink-0">

                <span class="text-3xl font-black text-cyan-600">
                    Novelion
                </span>

            </a>


            {{-- Search --}}
            <div class="relative w-full">

                <input
                    id="search-input"
                    type="text"
                    placeholder="Caută produse..."
                    autocomplete="off"
                    class="w-full rounded-full border border-gray-300 px-6 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-500">

                <div
                    id="search-results"
                    class="hidden absolute left-0 right-0 mt-2 bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden z-50">
                </div>

            </div>


            {{-- Favorite + Coș --}}
            <div class="flex items-center gap-5 shrink-0">

                @auth

                    <a
                        href="{{ route('wishlist.index') }}"
                        class="text-slate-600 hover:text-red-500 transition text-2xl"
                        aria-label="Favorite">

                        ❤️

                    </a>

                @else

                    <a
                        href="{{ route('login') }}"
                        class="text-slate-600 hover:text-red-500 transition text-2xl"
                        aria-label="Favorite">

                        ❤️

                    </a>

                @endauth


                {{-- Coș --}}
                <a
                    href="{{ route('cart.index') }}"
                    class="relative text-slate-600 hover:text-cyan-600 transition text-2xl"
                    aria-label="Coșul de cumpărături">

                    🛒

                    @php
                        $cartCount = session('cart')
                            ? array_sum(array_column(session('cart'), 'quantity'))
                            : 0;
                    @endphp

                    @if($cartCount)

                        <span class="absolute -top-2 -right-3 bg-red-500 text-white text-xs rounded-full px-2 py-0.5">
                            {{ $cartCount }}
                        </span>

                    @endif

                </a>

            </div>


            {{-- Utilizator --}}
            <div class="flex items-center justify-end gap-3 shrink-0">

                @auth

                    <a
                        href="{{ route('dashboard') }}"
                        class="font-semibold hover:text-cyan-600 transition whitespace-nowrap">

                        Contul meu

                    </a>

                @else

                    <a
                        href="{{ route('login') }}"
                        class="font-semibold hover:text-cyan-600 transition whitespace-nowrap">

                        Autentificare

                    </a>

                    <a
                        href="{{ route('register') }}"
                        class="bg-cyan-500 text-white px-5 py-2 rounded-xl hover:bg-cyan-600 transition font-semibold whitespace-nowrap">

                        Înregistrare

                    </a>

                @endauth

            </div>

        </div>


        {{-- MOBIL --}}
        <div class="lg:hidden flex flex-wrap items-center py-3">

            {{-- Logo --}}
            <a
                href="{{ route('home') }}"
                class="flex items-center shrink-0">

                <span class="text-3xl font-black text-cyan-600">
                    Novelion
                </span>

            </a>


            {{-- Favorite + Coș --}}
            <div class="flex items-center gap-4 ml-auto">

                @auth

                    <a
                        href="{{ route('wishlist.index') }}"
                        class="text-slate-600 hover:text-red-500 transition text-2xl"
                        aria-label="Favorite">

                        ❤️

                    </a>

                @else

                    <a
                        href="{{ route('login') }}"
                        class="text-slate-600 hover:text-red-500 transition text-2xl"
                        aria-label="Favorite">

                        ❤️

                    </a>

                @endauth


                <a
                    href="{{ route('cart.index') }}"
                    class="relative text-slate-600 hover:text-cyan-600 transition text-2xl"
                    aria-label="Coșul de cumpărături">

                    🛒

                    @php
                        $cartCount = session('cart')
                            ? array_sum(array_column(session('cart'), 'quantity'))
                            : 0;
                    @endphp

                    @if($cartCount)

                        <span class="absolute -top-2 -right-3 bg-red-500 text-white text-xs rounded-full px-2 py-0.5">
                            {{ $cartCount }}
                        </span>

                    @endif

                </a>

            </div>


            {{-- Search mobil --}}
            <div class="w-full mt-3">

                <div class="relative w-full">

                    <input
                        id="search-input-mobile"
                        type="text"
                        placeholder="Caută produse..."
                        autocomplete="off"
                        class="w-full rounded-full border border-gray-300 px-6 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-500">

                    <div
                        id="search-results-mobile"
                        class="hidden absolute left-0 right-0 mt-2 bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden z-50">
                    </div>

                </div>

            </div>


            {{-- Utilizator mobil --}}
            <div class="w-full mt-3 flex items-center justify-center gap-3">

                @auth

                    <a
                        href="{{ route('dashboard') }}"
                        class="font-semibold hover:text-cyan-600 transition">

                        Contul meu

                    </a>

                @else

                    <a
                        href="{{ route('login') }}"
                        class="font-semibold hover:text-cyan-600 transition">

                        Autentificare

                    </a>

                    <a
                        href="{{ route('register') }}"
                        class="bg-cyan-500 text-white px-5 py-2 rounded-xl hover:bg-cyan-600 transition font-semibold">

                        Înregistrare

                    </a>

                @endauth

            </div>

        </div>

    </div>

</header>