@php
    $safetyFields = [
        'manufacturer_name' => 'Producător',
        'manufacturer_contact' => 'Contact producător',
        'model_identifier' => 'Model / identificare',
        'eu_responsible_person_name' => 'Persoană responsabilă în UE',
        'eu_responsible_person_contact' => 'Contact persoană responsabilă în UE',
        'warnings' => 'Avertismente',
        'safety_instructions' => 'Instrucțiuni de siguranță',
        'commercial_warranty' => 'Garanție comercială a producătorului',
    ];
    $visibleSafetyFields = array_filter($safetyFields, fn ($label, $field) => filled(trim((string) $product->{$field})), ARRAY_FILTER_USE_BOTH);
@endphp

@if($visibleSafetyFields)
<section class="mt-14 border-t border-slate-200 pt-10" aria-labelledby="product-safety-heading">
    <h2 id="product-safety-heading" class="text-2xl sm:text-3xl font-bold text-slate-900 mb-6">Identificare și siguranță</h2>
    <div class="space-y-6 text-slate-700 leading-8 break-words">
        @foreach($visibleSafetyFields as $field => $label)
            <div>
                <h3 class="font-semibold text-slate-900 mb-2">{{ $label }}</h3>
                <div class="whitespace-pre-line">{{ trim($product->{$field}) }}</div>
            </div>
        @endforeach
    </div>
</section>
@endif
