<h1>{{ $returnRequest->type === 'withdrawal' ? 'Vreau să returnez un produs' : 'Produs defect/neconform' }}</h1>
<p>Am înregistrat solicitarea #{{ $returnRequest->id }} pentru comanda {{ $returnRequest->order->order_number }} la {{ $returnRequest->requested_at->format('d.m.Y H:i') }}.</p>
<ul>
@foreach($returnRequest->items as $returnItem)
    <li>{{ $returnItem->orderItem->product_name }} — {{ $returnItem->quantity }} buc.</li>
@endforeach
</ul>
@if($returnRequest->type === 'withdrawal')
<p>Această declarație reprezintă exercitarea dreptului de retragere. Costul direct al expedierii produselor la NOVELION S.R.L., Str. Daciei nr. 11, Ploiești, Prahova, 100352 este suportat de client.</p>
@else
<p>Fotografiile sunt opționale. Vom analiza reclamația în cadrul garanției legale de conformitate.</p>
@endif
<p>Contact: novelionprime@gmail.com · 0750 444 672, luni–vineri 09:00–17:00.</p>
