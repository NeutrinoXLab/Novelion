<h1>Comanda {{ $order->order_number }} a fost înregistrată</h1>
<p>Plata ramburs, în valoare de {{ number_format($order->total, 2, ',', '.') }} RON, este datorată la livrare.</p>
<ul>@foreach($order->items as $item)<li>{{ $item->product_name }} — {{ $item->quantity }} × {{ number_format($item->price, 2, ',', '.') }} RON</li>@endforeach</ul>
<p>Produse: {{ number_format($order->subtotal, 2, ',', '.') }} RON; livrare: {{ number_format($order->shipping_cost, 2, ',', '.') }} RON.</p>
<p>Livrare: {{ $order->shipping_first_name ?: $order->first_name }} {{ $order->shipping_last_name ?: $order->last_name }}, {{ $order->shipping_address ?: $order->address }}, {{ $order->shipping_city ?: $order->city }}, {{ $order->shipping_county ?: $order->county }}, {{ $order->shipping_postal_code ?: $order->postal_code }}.</p>
<p>Livrăm numai în România. Pregătirea și predarea către curier sunt distincte de timpul de transport; nu promitem un interval fix până la confirmarea serviciului.</p>
<p>Pentru consumatori, dreptul legal de retragere se poate exercita în 14 zile de la primirea produselor, inclusiv online din cont sau prin declarație neechivocă la novelionprime@gmail.com. Pentru simpla răzgândire, clientul suportă costul direct al returnării la adresa de mai jos. Produsele neconforme sunt gestionate separat în cadrul garanției legale, fără costurile remedierii. Excepțiile de la retragere sunt cele prevăzute de art. 16 OUG 34/2014.</p>
<p>Formular-model: Către NOVELION S.R.L., vă informez cu privire la retragerea din contract pentru [produse], comandate/primite la [date]. Nume, adresă, data și semnătura (numai pe hârtie). Utilizarea modelului nu este obligatorie.</p>
<p>Condițiile contractului, retururile, garanțiile și livrarea: {{ route('pages.terms') }} · {{ route('pages.returns') }} · {{ route('pages.shipping') }}.</p>
<p>NOVELION S.R.L., CUI 52627291, J2025075714007, Str. Daciei nr. 11, Ploiești, Prahova, 100352. Neplătitoare de TVA. novelionprime@gmail.com · 0750 444 672.</p>
