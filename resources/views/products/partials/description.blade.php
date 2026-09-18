@php
    $description = (string) $product->description;
    $containsHtml = $description !== strip_tags($description);
@endphp

<section class="mt-14 border-t border-slate-200 pt-10" aria-labelledby="product-description-heading">
    <h2 id="product-description-heading" class="text-3xl font-bold text-slate-900 mb-6">
        Descriere
    </h2>

    <div class="max-w-none text-slate-700 leading-8 [&_p]:mb-4 [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-slate-900 [&_h3]:mt-6 [&_h3]:mb-3 [&_h3]:text-xl [&_h3]:font-bold [&_h3]:text-slate-900 [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:pl-7 [&_ol]:mb-4 [&_ol]:list-decimal [&_ol]:pl-7 [&_li]:mb-1 [&_strong]:font-semibold [&_em]:italic [&_.color]:text-[var(--color)]">
        @if($containsHtml)
            {!! Filament\Forms\Components\RichEditor\RichContentRenderer::make($description)->toHtml() !!}
        @else
            {!! nl2br(e($description)) !!}
        @endif
    </div>
</section>
