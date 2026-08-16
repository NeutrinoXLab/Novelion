<footer class="mt-20 bg-slate-900 text-slate-300">

    <div class="max-w-7xl mx-auto px-6 py-16">

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10">

            {{-- NOVELION --}}
            <div>

                <h3 class="text-2xl font-bold text-white">
                    NOVELION
                </h3>

                <p class="mt-4 text-sm leading-7 text-slate-400">
                    Produse atent selecționate pentru casă, familie și stil de viață.
                    Ne dorim să oferim produse de calitate și o experiență excelentă la fiecare comandă.
                </p>

            </div>


            {{-- MAGAZIN --}}
            <div>

                <h4 class="text-lg font-semibold text-white mb-4">
                    Magazin
                </h4>

                <ul class="space-y-2">

                    <li>
                        <a
                            href="{{ route('products.index') }}"
                            class="hover:text-cyan-400 transition">
                            Produse
                        </a>
                    </li>

                    <li>
                        <a
                            href="{{ route('categories.index') }}"
                            class="hover:text-cyan-400 transition">
                            Categorii
                        </a>
                    </li>

                    <li>
                        <a
                            href="{{ route('products.new') }}"
                            class="hover:text-cyan-400 transition">
                            Produse noi
                        </a>
                    </li>

                    <li>
                        <a
                            href="{{ route('products.promotions') }}"
                            class="hover:text-cyan-400 transition">
                            Promoții
                        </a>
                    </li>

                </ul>

            </div>


            {{-- INFORMAȚII --}}
            <div>

                <h4 class="text-lg font-semibold text-white mb-4">
                    Informații
                </h4>

                <ul class="space-y-2">

                    <li>
                        <a
                            href="{{ route('pages.about') }}"
                            class="hover:text-cyan-400 transition">
                            Despre noi
                        </a>
                    </li>

                    <li>
                        <a
                            href="{{ route('pages.contact') }}"
                            class="hover:text-cyan-400 transition">
                            Contact
                        </a>
                    </li>

                    <li>
                        <a
                            href="{{ route('pages.shipping') }}"
                            class="hover:text-cyan-400 transition">
                            Livrare
                        </a>
                    </li>

                    <li>
                        <a
                            href="{{ route('pages.returns') }}"
                            class="hover:text-cyan-400 transition">
                            Retur
                        </a>
                    </li>

                    <li>
                        <a
                            href="{{ route('pages.terms') }}"
                            class="hover:text-cyan-400 transition">
                            Termeni și condiții
                        </a>
                    </li>

                    <li>
                        <a
                            href="{{ route('pages.privacy') }}"
                            class="hover:text-cyan-400 transition">
                            Politica de confidențialitate
                        </a>
                    </li>

                </ul>

            </div>


            {{-- CONTACT --}}
            <div>

                <h4 class="text-lg font-semibold text-white mb-4">
                    Contact
                </h4>

                <ul class="space-y-2">

                    <li>
                        📍 Ploiești, România
                    </li>

                    <li>
                        📞 0750 444 672
                    </li>

                    <li>
                        ✉️ novelionprime@gmail.com
                    </li>

                    <li>
                        Luni - Vineri<br>
                        09:00 - 17:00
                    </li>

                </ul>

            </div>

        </div>


        {{-- COPYRIGHT --}}
        <div class="border-t border-slate-700 mt-12 pt-6 flex flex-col md:flex-row justify-between items-center text-sm">

            <p>
                © {{ date('Y') }} NOVELION S.R.L. Toate drepturile rezervate.
            </p>

            <p class="mt-3 md:mt-0 text-slate-400">
                Mulțumim că ai ales Novelion ❤️
            </p>

        </div>

    </div>

</footer>