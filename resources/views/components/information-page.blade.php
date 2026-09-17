@props(['eyebrow', 'title'])

<div class="bg-slate-100 py-12 pb-28 sm:py-16">
    <div class="mx-auto max-w-5xl px-4 sm:px-6">
        <header class="mb-10 text-center sm:mb-12">
            <p class="text-sm font-semibold uppercase tracking-widest text-cyan-600">{{ $eyebrow }}</p>
            <h1 class="mt-3 text-4xl font-bold text-slate-900 md:text-5xl">{{ $title }}</h1>
        </header>

        <div class="rounded-3xl border border-slate-200 bg-white px-6 py-8 shadow-sm sm:p-10 lg:p-12">
            <div class="mx-auto max-w-3xl space-y-10 sm:space-y-12">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
