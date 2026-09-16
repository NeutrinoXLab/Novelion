<h1>Rambursare pentru comanda {{ $order->order_number }}</h1>
@if($state === 'completed')<p>Rambursarea {{ $amount ? 'de '.$amount.' RON' : '' }} a fost finalizată.</p>
@elseif($state === 'failed')<p>Procesatorul a raportat că rambursarea nu a reușit. Contactează-ne pentru soluționare.</p>
@else<p>Rambursarea {{ $amount ? 'de '.$amount.' RON' : '' }} a fost inițiată și este în curs de procesare. Îți vom confirma finalizarea separat.</p>@endif
<p>NOVELION S.R.L. · novelionprime@gmail.com · 0750 444 672</p>
