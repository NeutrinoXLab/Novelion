@extends('layouts.app')

@section('title', 'Solicită retur')

@section('content')

<section class="bg-slate-100 py-16 min-h-screen">

    <div class="max-w-4xl mx-auto px-6">

        {{-- HEADER --}}
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-6 mb-10">

            <div>

                <h1 class="text-4xl lg:text-5xl font-bold text-slate-900">
                    Solicită retur
                </h1>

                <p class="text-slate-500 mt-2">
                    Comanda {{ $order->order_number }}
                </p>

            </div>

            <a
                href="{{ route('my-orders.show', $order) }}"
                class="inline-block bg-white border border-slate-200 px-6 py-3 rounded-xl hover:bg-slate-50 transition text-center">

                ← Înapoi la comandă

            </a>

        </div>


        <div class="space-y-8">


            {{-- INFORMAȚII RETUR --}}
            <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                <h2 class="text-2xl font-bold text-slate-900 mb-4">
                    Retur în 30 de zile
                </h2>

                <p class="text-slate-600 leading-7">
                    Poți solicita returul produselor din această comandă în termen de
                    30 de zile calendaristice de la primirea produselor.
                </p>

                <p class="text-slate-500 text-sm mt-3">
                    Termenul expiră la
                    <span class="font-semibold text-slate-700">
                        {{ $order->delivered_at->copy()->addDays(30)->format('d.m.Y H:i') }}
                    </span>.
                </p>

            </div>


            {{-- PRODUSE COMANDATE --}}
            <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                <h2 class="text-2xl font-bold text-slate-900 mb-6">
                    Produse comandate
                </h2>

                <div class="space-y-5">

                    @foreach($order->items as $item)

                        <div class="flex flex-col sm:flex-row sm:justify-between gap-3 border-b border-slate-200 pb-5 last:border-b-0 last:pb-0">

                            <div>

                                <div class="font-semibold text-lg text-slate-900">
                                    {{ $item->product_name }}
                                </div>

                                <div class="text-slate-500 mt-1">
                                    Cantitate: {{ $item->quantity }}
                                </div>

                            </div>

                            <div class="font-bold text-cyan-600 text-lg sm:text-right">

                                {{ number_format($item->total, 2, ',', '.') }} Lei

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>


            {{-- FORMULAR RETUR --}}
            <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                <h2 class="text-2xl font-bold text-slate-900 mb-6">
                    Detalii retur
                </h2>

                <form
                    method="POST"
                    action="{{ route('returns.store', $order) }}"
                    class="space-y-6">

                    @csrf


                    {{-- MOTIV --}}
                    <div>

                        <label
                            for="reason"
                            class="block text-sm font-semibold text-slate-700 mb-2">

                            Motivul returului

                        </label>

                        <select
                            id="reason"
                            name="reason"
                            required
                            class="w-full rounded-xl border-slate-300 focus:border-cyan-500 focus:ring-cyan-500">

                            <option value="">
                                Selectează motivul
                            </option>

                            <option value="M-am răzgândit">
                                M-am răzgândit
                            </option>

                            <option value="Produsul nu corespunde așteptărilor">
                                Produsul nu corespunde așteptărilor
                            </option>

                            <option value="Produsul este deteriorat">
                                Produsul este deteriorat
                            </option>

                            <option value="Produsul este defect">
                                Produsul este defect
                            </option>

                            <option value="Am primit alt produs">
                                Am primit alt produs
                            </option>

                            <option value="Alt motiv">
                                Alt motiv
                            </option>

                        </select>

                        @error('reason')

                            <p class="text-red-600 text-sm mt-2">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>


                    {{-- OBSERVAȚII --}}
                    <div>

                        <label
                            for="notes"
                            class="block text-sm font-semibold text-slate-700 mb-2">

                            Observații
                            <span class="font-normal text-slate-400">
                                (opțional)
                            </span>

                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            rows="5"
                            maxlength="2000"
                            placeholder="Poți adăuga detalii despre retur..."
                            class="w-full rounded-xl border-slate-300 focus:border-cyan-500 focus:ring-cyan-500">{{ old('notes') }}</textarea>

                        @error('notes')

                            <p class="text-red-600 text-sm mt-2">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>


                    {{-- INFORMAȚIE --}}
                    <div class="bg-cyan-50 border border-cyan-100 rounded-2xl p-5">

                        <p class="text-sm text-cyan-900 leading-6">

                            După trimiterea solicitării, vom verifica cererea și te vom
                            contacta pentru pașii următori privind returul produselor.

                        </p>

                    </div>


                    {{-- BUTOANE --}}
                    <div class="flex flex-col sm:flex-row gap-4 pt-2">

                        <a
                            href="{{ route('my-orders.show', $order) }}"
                            class="w-full sm:w-auto px-6 py-3 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-center transition">

                            Anulează

                        </a>

                        <button
                            type="submit"
                            class="w-full sm:flex-1 bg-cyan-500 hover:bg-cyan-600 text-white py-3 rounded-xl font-semibold text-center transition">

                            Trimite solicitarea de retur

                        </button>

                    </div>

                </form>

            </div>


        </div>

    </div>

</section>

@endsection