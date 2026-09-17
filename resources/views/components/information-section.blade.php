@props(['title'])

<section {{ $attributes->class(['space-y-4']) }}>
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <div class="space-y-4 leading-8 text-slate-600">
        {{ $slot }}
    </div>
</section>
