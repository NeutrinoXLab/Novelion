<!DOCTYPE html>
<html lang="ro">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', 'Novelion')
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="bg-slate-100 text-slate-800">

    @include('components.header')


    {{-- Mesaj de succes --}}
    @if(session('success'))

        <div class="max-w-7xl mx-auto px-6 mt-6">

            <div class="bg-green-100 border border-green-300 text-green-800 rounded-2xl px-6 py-4">

                {{ session('success') }}

            </div>

        </div>

    @endif


    {{-- Mesaj informativ --}}
    @if(session('info'))

        <div class="max-w-7xl mx-auto px-6 mt-6">

            <div class="bg-blue-100 border border-blue-300 text-blue-800 rounded-2xl px-6 py-4">

                {{ session('info') }}

            </div>

        </div>

    @endif


    {{-- Mesaj de eroare --}}
    @if(session('error'))

        <div class="max-w-7xl mx-auto px-6 mt-6">

            <div class="bg-red-100 border border-red-300 text-red-800 rounded-2xl px-6 py-4">

                {{ session('error') }}

            </div>

        </div>

    @endif


    <main>

        @yield('content')

    </main>


    {{-- Chat Novelion --}}
    <x-chat />


    {{-- WhatsApp --}}
    <a
        href="https://wa.me/40750444672"
        target="_blank"
        rel="noopener noreferrer"
        class="fixed bottom-6 right-6 z-50
               w-16 h-16
               bg-green-500 hover:bg-green-600
               text-white
               rounded-full
               shadow-lg
               flex items-center justify-center
               text-3xl
               transition
               hover:scale-110"
        aria-label="Contactează-ne pe WhatsApp"
    >
        💬
    </a>


    {{-- Footer --}}
    @include('components.footer')


</body>
</html>
