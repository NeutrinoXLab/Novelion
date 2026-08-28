@extends('layouts.app')

@section('title', 'Profilul meu')

@section('content')

<section class="bg-slate-50 min-h-screen py-12">

```
<div class="max-w-5xl mx-auto px-6">

    {{-- HEADER --}}
    <div class="mb-10">

        <a
            href="{{ route('dashboard') }}"
            class="text-cyan-600 hover:underline font-semibold">

            ← Înapoi în cont

        </a>

        <h1 class="text-4xl font-black text-slate-900 mt-5">
            Profilul meu
        </h1>

        <p class="text-slate-500 mt-2">
            Gestionează datele personale, parola și contul tău Novelion.
        </p>

    </div>


    {{-- MESAJ SUCCES --}}
    @if (session('status') === 'profile-updated')

        <div class="mb-8 rounded-2xl bg-green-50 border border-green-200 p-5 text-green-700 font-semibold">

            Datele profilului au fost actualizate cu succes.

        </div>

    @endif


    {{-- DATE PERSONALE --}}
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 mb-8">

        <h2 class="text-2xl font-bold text-slate-900">
            Date personale
        </h2>

        <p class="text-slate-500 mt-2 mb-8">
            Actualizează numele și adresa de email.
        </p>

        <form
            method="POST"
            action="{{ route('profile.update') }}"
            class="space-y-6">

            @csrf
            @method('PATCH')

            <div>

                <label
                    for="name"
                    class="block font-semibold text-slate-700 mb-2">

                    Nume

                </label>

                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $user->name) }}"
                    required
                    autofocus
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500">

                @error('name')

                    <p class="text-red-500 text-sm mt-2">
                        {{ $message }}
                    </p>

                @enderror

            </div>


            <div>

                <label
                    for="email"
                    class="block font-semibold text-slate-700 mb-2">

                    Email

                </label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', $user->email) }}"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500">

                @error('email')

                    <p class="text-red-500 text-sm mt-2">
                        {{ $message }}
                    </p>

                @enderror

            </div>


            <button
                type="submit"
                class="bg-cyan-500 hover:bg-cyan-600 text-white px-6 py-3 rounded-xl font-bold transition">

                Salvează modificările

            </button>

        </form>

    </div>


    {{-- PAROLĂ --}}
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 mb-8">

        <h2 class="text-2xl font-bold text-slate-900">
            Schimbă parola
        </h2>

        <p class="text-slate-500 mt-2 mb-8">
            Folosește o parolă puternică pentru a-ți proteja contul.
        </p>

        @if (session('status') === 'password-updated')

            <div class="mb-6 rounded-2xl bg-green-50 border border-green-200 p-5 text-green-700 font-semibold">

                Parola a fost schimbată cu succes.

            </div>

        @endif


        <form
            method="POST"
            action="{{ route('password.update') }}"
            class="space-y-6">

            @csrf
            @method('PUT')

            <div>

                <label
                    for="current_password"
                    class="block font-semibold text-slate-700 mb-2">

                    Parola actuală

                </label>

                <input
                    id="current_password"
                    name="current_password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500">

                @error('current_password', 'updatePassword')

                    <p class="text-red-500 text-sm mt-2">
                        {{ $message }}
                    </p>

                @enderror

            </div>


            <div>

                <label
                    for="password"
                    class="block font-semibold text-slate-700 mb-2">

                    Parolă nouă

                </label>

                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500">

                @error('password', 'updatePassword')

                    <p class="text-red-500 text-sm mt-2">
                        {{ $message }}
                    </p>

                @enderror

            </div>


            <div>

                <label
                    for="password_confirmation"
                    class="block font-semibold text-slate-700 mb-2">

                    Confirmă parola nouă

                </label>

                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500">

            </div>


            <button
                type="submit"
                class="bg-cyan-500 hover:bg-cyan-600 text-white px-6 py-3 rounded-xl font-bold transition">

                Schimbă parola

            </button>

        </form>

    </div>


    {{-- ȘTERGERE CONT --}}
    <div class="bg-white rounded-3xl shadow-sm border border-red-200 p-8">

        <h2 class="text-2xl font-bold text-slate-900">
            Șterge contul
        </h2>

        <p class="text-slate-500 mt-2 mb-8">
            Ștergerea contului este permanentă și nu poate fi anulată.
        </p>

        <form
            method="POST"
            action="{{ route('profile.destroy') }}"
            onsubmit="return confirm('Ești sigur că vrei să ștergi definitiv contul?');">

            @csrf
            @method('DELETE')

            <div class="mb-6">

                <label
                    for="delete_password"
                    class="block font-semibold text-slate-700 mb-2">

                    Parola actuală

                </label>

                <input
                    id="delete_password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-red-500 focus:ring-red-500">

                @error('password', 'userDeletion')

                    <p class="text-red-500 text-sm mt-2">
                        {{ $message }}
                    </p>

                @enderror

            </div>

            <button
                type="submit"
                class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-xl font-bold transition">

                Șterge definitiv contul

            </button>

        </form>

    </div>

</div>
```

</section>

@endsection
