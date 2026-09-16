<!DOCTYPE html>
<html lang="ro">

<head>
    <meta charset="UTF-8">
    <title>Comanda a fost anulată</title>
</head>

<body style="margin:0;padding:40px;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td align="center">

<table width="700" cellspacing="0" cellpadding="0"
style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 25px rgba(0,0,0,.08);">

<!-- HEADER -->

<tr>

<td style="background:#0f172a;padding:45px;text-align:center;">

<h1 style="margin:0;font-size:42px;color:white;">

Novelion

</h1>

<p style="margin-top:12px;font-size:18px;color:#d6e4ff;">

❌ Comanda ta a fost anulată

</p>

<p style="margin-top:8px;font-size:15px;color:#cbd5e1;">

Comanda nu va mai fi procesată.

</p>

</td>

</tr>

<!-- CONTENT -->

<tr>

<td style="padding:45px;">

<h2 style="margin-top:0;color:#0f172a;">

Salut,

<strong>

{{ $order->shipping_first_name ?: $order->first_name }} {{ $order->shipping_last_name ?: $order->last_name }}

</strong>

👋

</h2>

<p style="font-size:17px;color:#555;line-height:30px;">

Comanda

<strong>{{ $order->order_number }}</strong>

a fost anulată.

@if($order->refund_status === 'completed')
Rambursarea a fost confirmată de procesator.
@elseif($order->refund_status === 'processing' || $order->refund_status === 'initiated')
Rambursarea este în curs; nu este încă finalizată.
@else
Comanda neplătită a fost anulată; nu există o rambursare aferentă.
@endif

<br><br>

Dacă aceasta a fost o anulare solicitată de tine, nu mai este necesară nicio acțiune.

</p>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<h3 style="color:#0f172a;">

Produse din comandă

</h3>

<table width="100%" cellspacing="0" cellpadding="12">

<thead>

<tr style="background:#eef2f7;">

<th align="left">Produs</th>

<th align="center">Cantitate</th>

<th align="right">Total</th>

</tr>

</thead>

<tbody>

@foreach($order->items as $item)

<tr>

<td>

<strong>{{ $item->product_name }}</strong>

</td>

<td align="center">

{{ $item->quantity }}

</td>

<td align="right">

{{ number_format($item->total,2,',','.') }} RON

</td>

</tr>

@endforeach

</tbody>

</table>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<table width="100%" cellpadding="8">

<tr>

<td>

Subtotal produse

</td>

<td align="right">

{{ number_format($order->subtotal,2,',','.') }} RON

</td>

</tr>

<tr>

<td>

Transport

</td>

<td align="right">

{{ number_format($order->shipping_cost,2,',','.') }} RON

</td>

</tr>

<tr style="font-size:22px;font-weight:bold;">

<td>

Total comandă

</td>

<td align="right">

{{ number_format($order->total,2,',','.') }} RON

</td>

</tr>

</table>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<h3 style="color:#0f172a;">

Adresa de livrare

</h3>

<p style="line-height:30px;">

<strong>

{{ $order->shipping_first_name ?: $order->first_name }} {{ $order->shipping_last_name ?: $order->last_name }}

</strong>

<br>

{{ $order->shipping_address ?: $order->address }}

<br>

{{ $order->shipping_postal_code ?: $order->postal_code }} {{ $order->shipping_city ?: $order->city }}

<br>

{{ $order->shipping_county ?: $order->county }}

</p>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<p style="font-size:18px;">

Status comandă:

<strong style="color:#dc2626;">

❌ Anulată

</strong>

</p>

<p style="font-size:16px;color:#555;line-height:28px;">

Dacă plata a fost deja efectuată, rambursarea va fi procesată conform politicii noastre.

<br><br>

Dacă ai întrebări sau consideri că această anulare este o eroare, ne poți contacta oricând și vom încerca să te ajutăm cât mai repede.

</p>

</td>

</tr>

<!-- FOOTER -->

<tr>

<td style="background:#0f172a;color:white;padding:35px;text-align:center;">

<h2 style="margin:0;">

Novelion

</h2>

<p style="margin-top:15px;color:#d6e4ff;">

Îți mulțumim pentru încredere!

</p>

<p style="margin-top:8px;color:#d6e4ff;">

novelionprime@gmail.com

</p>

<p style="margin-top:25px;font-size:13px;color:#94a3b8;">

© {{ date('Y') }} Novelion. Toate drepturile rezervate.

</p>

</td>

</tr>

</table>

</td>

</tr>

</table>

</body>

</html>
