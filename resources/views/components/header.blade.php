<header class="bg-white shadow-sm border-b border-slate-200 sticky top-0 z-50">

    <div class="max-w-7xl mx-auto px-6">

        <div class="h-20 flex items-center">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex items-center shrink-0">

                <span class="text-3xl font-black text-cyan-600">
                    Novelion
                </span>

            </a>

            {{-- Search --}}
            <div class="flex-1">

                <div class="flex-1 max-w-xl mx-10 relative">

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

            </div>

            {{-- Meniu dreapta --}}
            <div class="flex items-center gap-6 ml-8">

                {{-- Favorite --}}
                @auth

                    <a
                        href="{{ route('wishlist.index') }}"
                        class="text-slate-600 hover:text-red-500 transition text-2xl">

                        ❤️

                    </a>

                @else

                    <a
                        href="{{ route('login') }}"
                        class="text-slate-600 hover:text-red-500 transition text-2xl">

                        ❤️

                    </a>

                @endauth

                {{-- Coș --}}
                <a
                    href="{{ route('cart.index') }}"
                    class="relative text-slate-600 hover:text-cyan-600 transition text-2xl">

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

                {{-- Utilizator --}}
                @auth

                    <a
                        href="{{ route('dashboard') }}"
                        class="font-semibold hover:text-cyan-600">

                        Contul meu

                    </a>

                @else

                    <a
                        href="{{ route('login') }}"
                        class="font-semibold hover:text-cyan-600">

                        Autentificare

                    </a>

                    <a
                        href="{{ route('register') }}"
                        class="bg-cyan-500 text-white px-5 py-2 rounded-xl hover:bg-cyan-600 transition">

                        Înregistrare

                    </a>

                @endauth

            </div>

        </div>

    </div>

</header>