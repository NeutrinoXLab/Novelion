@extends('layouts.app')

@section('title', 'Finalizare comandă')

@section('content')

<section class="bg-slate-100 py-14">

    <div class="max-w-7xl mx-auto px-6">

        <h1 class="text-5xl font-bold mb-10">
            Finalizare comandă
        </h1>

        <form action="{{ route('checkout.store') }}" method="POST">

            @csrf

            <div class="grid lg:grid-cols-3 gap-10">

                {{-- ========================================================= --}}
                {{-- DATE CLIENT --}}
                {{-- ========================================================= --}}

                <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm p-8">

                    <h2 class="text-2xl font-bold mb-8">
                        Date facturare
                    </h2>

                    {{-- ===================================================== --}}
                    {{-- TIP CLIENT --}}
                    {{-- ===================================================== --}}

                    <div class="mb-8">

                        <label class="block mb-3 font-semibold">
                            Tip client
                        </label>

                        <div class="flex gap-6">

                            <label class="flex items-center gap-2">

                                <input
                                    type="radio"
                                    name="customer_type"
                                    value="individual"
                                    {{ old('customer_type', 'individual') === 'individual' ? 'checked' : '' }}
                                    onclick="toggleCompanyFields()">

                                Persoană fizică

                            </label>

                            <label class="flex items-center gap-2">

                                <input
                                    type="radio"
                                    name="customer_type"
                                    value="company"
                                    {{ old('customer_type') === 'company' ? 'checked' : '' }}
                                    onclick="toggleCompanyFields()">

                                Persoană juridică

                            </label>

                        </div>

                    </div>

                    {{-- ===================================================== --}}
                    {{-- DATE FIRMĂ --}}
                    {{-- ===================================================== --}}

                    <div
                        id="companyFields"
                        class="hidden border rounded-2xl bg-slate-50 p-6 mb-8">

                        <h3 class="text-xl font-bold mb-6">
                            Date firmă
                        </h3>

                        <div class="grid md:grid-cols-2 gap-6">

                            <div>

                                <label class="block mb-2 font-semibold">
                                    Denumire firmă
                                </label>

                                <input
                                    type="text"
                                    name="company_name"
                                    value="{{ old('company_name') }}"
                                    class="w-full rounded-xl border border-slate-300">

                                @error('company_name')
                                    <p class="mt-1 text-sm text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                            <div>

                                <label class="block mb-2 font-semibold">
                                    CUI
                                </label>

                                <input
                                    type="text"
                                    name="company_vat"
                                    value="{{ old('company_vat') }}"
                                    class="w-full rounded-xl border border-slate-300">

                                @error('company_vat')
                                    <p class="mt-1 text-sm text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                            <div>

                                <label class="block mb-2 font-semibold">
                                    Nr. Registrul Comerțului
                                </label>

                                <input
                                    type="text"
                                    name="company_registration"
                                    value="{{ old('company_registration') }}"
                                    class="w-full rounded-xl border border-slate-300">

                            </div>

                            <div>

                                <label class="block mb-2 font-semibold">
                                    Județ firmă
                                </label>

                                <input
                                    type="text"
                                    name="company_county"
                                    value="{{ old('company_county') }}"
                                    class="w-full rounded-xl border border-slate-300">

                            </div>

                            <div>

                                <label class="block mb-2 font-semibold">
                                    Oraș firmă
                                </label>

                                <input
                                    type="text"
                                    name="company_city"
                                    value="{{ old('company_city') }}"
                                    class="w-full rounded-xl border border-slate-300">

                            </div>

                            <div class="md:col-span-2">

                                <label class="block mb-2 font-semibold">
                                    Adresă firmă
                                </label>

                                <input
                                    type="text"
                                    name="company_address"
                                    value="{{ old('company_address') }}"
                                    class="w-full rounded-xl border border-slate-300">

                            </div>

                        </div>

                    </div>

                    {{-- ===================================================== --}}
                    {{-- DATE FACTURARE --}}
                    {{-- ===================================================== --}}

                    <div class="grid md:grid-cols-2 gap-6">

                        {{-- Prenume --}}
                        <div>

                            <label class="block mb-2 font-semibold">
                                Prenume
                            </label>

                            <input
                                type="text"
                                name="first_name"
                                value="{{ old('first_name', auth()->user()->name ?? '') }}"
                                class="w-full rounded-xl border {{ $errors->has('first_name') ? 'border-red-500' : 'border-slate-300' }}">

                            @error('first_name')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        {{-- Nume --}}
                        <div>

                            <label class="block mb-2 font-semibold">
                                Nume
                            </label>

                            <input
                                type="text"
                                name="last_name"
                                value="{{ old('last_name') }}"
                                class="w-full rounded-xl border {{ $errors->has('last_name') ? 'border-red-500' : 'border-slate-300' }}">

                            @error('last_name')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        {{-- Email --}}
                        <div>

                            <label class="block mb-2 font-semibold">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                value="{{ old('email', auth()->user()->email ?? '') }}"
                                class="w-full rounded-xl border {{ $errors->has('email') ? 'border-red-500' : 'border-slate-300' }}">

                            @error('email')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        {{-- Telefon --}}
                        <div>

                            <label class="block mb-2 font-semibold">
                                Telefon
                            </label>

                            <input
                                type="text"
                                name="phone"
                                value="{{ old('phone') }}"
                                class="w-full rounded-xl border {{ $errors->has('phone') ? 'border-red-500' : 'border-slate-300' }}">

                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        {{-- Județ --}}
                        <div>

                            <label class="block mb-2 font-semibold">
                                Județ
                            </label>

                            <input
                                type="text"
                                name="county"
                                value="{{ old('county') }}"
                                class="w-full rounded-xl border {{ $errors->has('county') ? 'border-red-500' : 'border-slate-300' }}">

                            @error('county')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        {{-- Oraș --}}
                        <div>

                            <label class="block mb-2 font-semibold">
                                Oraș
                            </label>

                            <input
                                type="text"
                                name="city"
                                value="{{ old('city') }}"
                                class="w-full rounded-xl border {{ $errors->has('city') ? 'border-red-500' : 'border-slate-300' }}">

                            @error('city')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        {{-- Adresă --}}
                        <div class="md:col-span-2">

                            <label class="block mb-2 font-semibold">
                                Adresă
                            </label>

                            <input
                                type="text"
                                name="address"
                                value="{{ old('address') }}"
                                class="w-full rounded-xl border {{ $errors->has('address') ? 'border-red-500' : 'border-slate-300' }}">

                            @error('address')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        {{-- Cod poștal --}}
                        <div>

                            <label class="block mb-2 font-semibold">
                                Cod poștal
                            </label>

                            <input
                                type="text"
                                name="postal_code"
                                value="{{ old('postal_code') }}"
                                class="w-full rounded-xl border {{ $errors->has('postal_code') ? 'border-red-500' : 'border-slate-300' }}">

                            @error('postal_code')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        {{-- ================================================= --}}
                        {{-- ADRESĂ LIVRARE DIFERITĂ --}}
                        {{-- ================================================= --}}

                        <div class="md:col-span-2">

                            <hr class="my-8">

                            <label class="flex items-center gap-3 cursor-pointer">

                                <input
                                    type="checkbox"
                                    id="differentShipping"
                                    {{ old('shipping_address') ? 'checked' : '' }}
                                    onclick="toggleShippingFields()">

                                <span class="font-semibold">
                                    Adresa de livrare este diferită de adresa de facturare
                                </span>

                            </label>

                        </div>

                        {{-- ================================================= --}}
                        {{-- DATE LIVRARE --}}
                        {{-- ================================================= --}}

                        <div
                            id="shippingFields"
                            class="hidden md:col-span-2 border rounded-2xl bg-slate-50 p-6">

                            <h2 class="text-2xl font-bold mb-6">
                                Date livrare
                            </h2>

                            <div class="grid md:grid-cols-2 gap-6">

                                <div>

                                    <label class="block mb-2 font-semibold">
                                        Prenume
                                    </label>

                                    <input
                                        type="text"
                                        name="shipping_first_name"
                                        value="{{ old('shipping_first_name') }}"
                                        class="w-full rounded-xl border border-slate-300">

                                </div>

                                <div>

                                    <label class="block mb-2 font-semibold">
                                        Nume
                                    </label>

                                    <input
                                        type="text"
                                        name="shipping_last_name"
                                        value="{{ old('shipping_last_name') }}"
                                        class="w-full rounded-xl border border-slate-300">

                                </div>

                                <div>

                                    <label class="block mb-2 font-semibold">
                                        Telefon
                                    </label>

                                    <input
                                        type="text"
                                        name="shipping_phone"
                                        value="{{ old('shipping_phone') }}"
                                        class="w-full rounded-xl border border-slate-300">

                                </div>

                                <div>

                                    <label class="block mb-2 font-semibold">
                                        Județ
                                    </label>

                                    <input
                                        type="text"
                                        name="shipping_county"
                                        value="{{ old('shipping_county') }}"
                                        class="w-full rounded-xl border border-slate-300">

                                </div>

                                <div>

                                    <label class="block mb-2 font-semibold">
                                        Oraș
                                    </label>

                                    <input
                                        type="text"
                                        name="shipping_city"
                                        value="{{ old('shipping_city') }}"
                                        class="w-full rounded-xl border border-slate-300">

                                </div>

                                <div>

                                    <label class="block mb-2 font-semibold">
                                        Cod poștal
                                    </label>

                                    <input
                                        type="text"
                                        name="shipping_postal_code"
                                        value="{{ old('shipping_postal_code') }}"
                                        class="w-full rounded-xl border border-slate-300">

                                </div>

                                <div class="md:col-span-2">

                                    <label class="block mb-2 font-semibold">
                                        Adresă
                                    </label>

                                    <input
                                        type="text"
                                        name="shipping_address"
                                        value="{{ old('shipping_address') }}"
                                        class="w-full rounded-xl border border-slate-300">

                                </div>

                            </div>

                        </div>

                        {{-- ================================================= --}}
                        {{-- OBSERVAȚII --}}
                        {{-- ================================================= --}}

                        <div class="md:col-span-2">

                            <label class="block mb-2 font-semibold">
                                Observații
                            </label>

                            <textarea
                                name="notes"
                                rows="4"
                                class="w-full rounded-xl border {{ $errors->has('notes') ? 'border-red-500' : 'border-slate-300' }}">{{ old('notes') }}</textarea>

                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        {{-- ================================================= --}}
                        {{-- METODA DE PLATĂ --}}
                        {{-- ================================================= --}}

                        <div class="md:col-span-2 mt-8">

                            <h2 class="text-2xl font-bold mb-6">
                                Metoda de plată
                            </h2>

                            <div class="space-y-4">

                                <label class="flex items-center gap-4 border rounded-2xl p-5 cursor-pointer hover:border-cyan-500 transition">

                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="cash"
                                        {{ old('payment_method', 'cash') === 'cash' ? 'checked' : '' }}
                                        class="w-5 h-5 text-cyan-600">

                                    <div>

                                        <div class="font-bold">
                                            💵 Ramburs
                                        </div>

                                        <div class="text-slate-500 text-sm">
                                            Plătești numerar la livrare.
                                        </div>

                                    </div>

                                </label>

                                <label class="flex items-center gap-4 border rounded-2xl p-5 cursor-pointer hover:border-cyan-500 transition">

                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="stripe"
                                        {{ old('payment_method') === 'stripe' ? 'checked' : '' }}
                                        class="w-5 h-5 text-cyan-600">

                                    <div>

                                        <div class="font-bold">
                                            💳 Card bancar (Stripe)
                                        </div>

                                        <div class="text-slate-500 text-sm">
                                            Plată securizată prin Stripe.
                                        </div>

                                    </div>

                                </label>

                            </div>

                        </div>

                    </div>

                </div>

                {{-- ========================================================= --}}
                {{-- REZUMAT COMANDĂ --}}
                {{-- ========================================================= --}}

                <div>

                    <div class="bg-white rounded-3xl shadow-sm p-8 sticky top-10">

                        <h2 class="text-2xl font-bold mb-8">
                            Rezumat comandă
                        </h2>

                        {{-- PRODUSE --}}
                        <div class="space-y-4">

                            @foreach($items as $item)

                                <div class="flex justify-between gap-4">

                                    <div>

                                        <div class="font-semibold text-slate-900">
                                            {{ $item['product']->name }}
                                        </div>

                                        <div class="text-sm text-slate-500">
                                            Cantitate: {{ $item['quantity'] }}
                                        </div>

                                    </div>

                                    <div class="font-semibold whitespace-nowrap">

                                        {{ number_format($item['subtotal'], 2, ',', '.') }} Lei

                                    </div>

                                </div>

                            @endforeach

                        </div>

                        <hr class="my-8">

                        {{-- SUBTOTAL PRODUSE --}}
                        <div class="flex justify-between text-lg mb-4">

                            <span class="text-slate-600">
                                Produse
                            </span>

                            <span class="font-semibold">
                                {{ number_format($subtotal, 2, ',', '.') }} Lei
                            </span>

                        </div>

                        {{-- TRANSPORT --}}
                        <div class="flex justify-between text-lg mb-4">

                            <span class="text-slate-600">

                                Livrare

                                @if($shippingName)

                                    <span class="text-sm text-slate-400">
                                        ({{ $shippingName }})
                                    </span>

                                @endif

                            </span>

                            @if($shippingCost > 0)

                                <span class="font-semibold">

                                    {{ number_format($shippingCost, 2, ',', '.') }} Lei

                                </span>

                            @else

                                <span class="font-semibold text-green-600">
                                    Gratuit
                                </span>

                            @endif

                        </div>

                        <hr class="my-6">

                        {{-- TOTAL --}}
                        <div class="flex justify-between items-center">

                            <span class="text-2xl font-bold">
                                Total
                            </span>

                            <span class="text-3xl font-bold text-cyan-600">

                                {{ number_format($total, 2, ',', '.') }} Lei

                            </span>

                        </div>

                        {{-- BUTON --}}
                        <button
                            type="submit"
                            class="w-full mt-8 bg-cyan-500 hover:bg-cyan-600 text-white py-4 rounded-2xl font-bold transition">

                            Plasează comanda

                        </button>

                    </div>

                </div>

            </div>

        </form>

    </div>

</section>

<script>

function toggleCompanyFields()
{
    const selected = document.querySelector(
        'input[name="customer_type"]:checked'
    );

    const company = document.getElementById('companyFields');

    if (!selected || !company) {
        return;
    }

    if (selected.value === 'company') {

        company.classList.remove('hidden');

    } else {

        company.classList.add('hidden');

    }
}

function toggleShippingFields()
{
    const checkbox = document.getElementById('differentShipping');
    const shipping = document.getElementById('shippingFields');

    if (!checkbox || !shipping) {
        return;
    }

    if (checkbox.checked) {

        shipping.classList.remove('hidden');

    } else {

        shipping.classList.add('hidden');

    }
}

document.addEventListener('DOMContentLoaded', function () {

    toggleCompanyFields();
    toggleShippingFields();

});

</script>

@endsection