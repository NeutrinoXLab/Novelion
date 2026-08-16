@extends('layouts.app')

@section('title', 'Despre noi - Novelion')

@section('content')

<div class="bg-slate-100 py-16">

    <div class="max-w-5xl mx-auto px-6">

        {{-- Titlu --}}
        <div class="text-center mb-12">

            <p class="text-cyan-600 font-semibold uppercase tracking-widest text-sm">
                Despre Novelion
            </p>

            <h1 class="mt-3 text-4xl md:text-5xl font-bold text-slate-900">
                Bine ai venit la NOVELION
            </h1>

            <p class="mt-5 text-lg text-slate-600 max-w-3xl mx-auto leading-8">
                Descoperă un magazin online creat cu grijă pentru oameni care
                apreciază produsele utile, calitatea și o experiență simplă de cumpărare.
            </p>

        </div>


        {{-- Conținut principal --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-12">

            <div class="space-y-10">

                <section>

                    <h2 class="text-2xl font-bold text-slate-900">
                        Cine suntem
                    </h2>

                    <p class="mt-4 text-slate-600 leading-8">
                        NOVELION este un magazin online românesc în care selectăm
                        produse pentru casă, familie și stil de viață.
                        Ne concentrăm pe produse pe care le considerăm utile,
                        interesante și potrivite pentru viața de zi cu zi.
                    </p>

                </section>


                <section>

                    <h2 class="text-2xl font-bold text-slate-900">
                        Ce ne propunem
                    </h2>

                    <p class="mt-4 text-slate-600 leading-8">
                        Ne dorim să construim un magazin în care cumpărăturile să fie
                        cât mai simple și transparente. De la alegerea produsului
                        până la livrare, punem accent pe informații clare,
                        comunicare și o experiență plăcută pentru fiecare client.
                    </p>

                </section>


                <section>

                    <h2 class="text-2xl font-bold text-slate-900">
                        Produse atent selecționate
                    </h2>

                    <p class="mt-4 text-slate-600 leading-8">
                        Catalogul NOVELION este construit treptat, prin selectarea
                        produselor pe care le considerăm potrivite pentru clienții
                        noștri. Preferăm să construim un catalog echilibrat și util,
                        în loc să oferim produse fără o selecție atentă.
                    </p>

                </section>


                <section>

                    <h2 class="text-2xl font-bold text-slate-900">
                        Experiența clientului
                    </h2>

                    <p class="mt-4 text-slate-600 leading-8">
                        Credem că un magazin online bun nu înseamnă doar produse și
                        prețuri. Înseamnă și informații corecte, comenzi ușor de
                        plasat, metode de plată convenabile și comunicare atunci
                        când clientul are nevoie de ajutor.
                    </p>

                </section>


                {{-- Mesaj final --}}
                <div class="border-t border-slate-200 pt-10">

                    <div class="bg-cyan-50 rounded-2xl p-6 md:p-8">

                        <h2 class="text-xl font-bold text-slate-900">
                            Mulțumim că ai ales NOVELION
                        </h2>

                        <p class="mt-3 text-slate-600 leading-7">
                            Suntem la început de drum și construim magazinul
                            pas cu pas. Fiecare comandă și fiecare client
                            contribuie la dezvoltarea NOVELION.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection