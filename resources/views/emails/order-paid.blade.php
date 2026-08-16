<!DOCTYPE html>
<html lang="ro">

<head>
    <meta charset="UTF-8">
    <title>Comandă confirmată</title>
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
Comanda ta a fost confirmată ✔
</p>

<p style="margin-top:8px;font-size:15px;color:#cbd5e1;">
Îți mulțumim că ai ales Novelion.
</p>

<p style="margin-top:22px;color:white;font-size:16px;">

Comanda

<strong>

{{ $order->order_number }}

</strong>

</p>

</td>
</tr>

<!-- CONTENT -->

<tr>

<td style="padding:45px;">

<h2 style="margin-top:0;color:#0f172a;">

Salut,

<strong>

{{ $order->first_name }}
{{ $order->last_name }}

</strong>

👋

</h2>

<p style="font-size:17px;color:#555;line-height:30px;">

Plata a fost confirmată cu succes.

<br><br>

Comanda ta este deja în curs de procesare.

În curând o vom pregăti pentru expediere.

</p>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<h3 style="color:#0f172a;">
Produse comandate
</h3>

<table width="100%" cellspacing="0" cellpadding="12">

<thead>

<tr style="background:#eef2f7;">

<th align="left">

Produs

</th>

<th align="center">

Cantitate

</th>

<th align="right">

Preț unitar

</th>

<th align="right">

Total

</th>

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

{{ number_format($item->price,2,',','.') }} RON

</td>

<td align="right">

<strong>

{{ number_format($item->total,2,',','.') }} RON

</strong>

</td>

</tr>

@endforeach

</tbody>

</table>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<table width="100%" cellpadding="8">

<tr>

<td style="color:#666;">

Subtotal produse

</td>

<td align="right">

{{ number_format($order->subtotal,2,',','.') }} RON

</td>

</tr>

<tr>

<td style="color:#666;">

Transport

</td>

<td align="right">

{{ number_format($order->shipping_cost,2,',','.') }} RON

</td>

</tr>

<tr>

<td style="color:#666;">

Metodă de plată

</td>

<td align="right">

Card bancar (Stripe)

</td>

</tr>

<tr style="font-size:23px;font-weight:bold;">

<td style="padding-top:15px;">

TOTAL PLĂTIT

</td>

<td align="right" style="padding-top:15px;">

{{ number_format($order->total,2,',','.') }} RON

</td>

</tr>

</table>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<h3 style="color:#0f172a;">

Date de livrare

</h3>

<p style="line-height:30px;">

<strong>

{{ $order->first_name }}
{{ $order->last_name }}

</strong>

<br>

{{ $order->address }}

<br>

{{ $order->postal_code }}
{{ $order->city }}

<br>

{{ $order->county }}

<br><br>

Telefon:
{{ $order->phone }}

<br>

Email:
{{ $order->email }}

</p>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<h3 style="color:#0f172a;">

Status comandă

</h3>

<p>

<span style="background:#fff7ed;
padding:10px 18px;
border-radius:6px;
font-weight:bold;
color:#d97706;">

🟡 În pregătire

</span>

</p>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<h3 style="color:#0f172a;">

Ce urmează?

</h3>

<ul style="line-height:34px;color:#555;">

<li>✔ Verificăm produsele.</li>

<li>✔ Ambalăm coletul.</li>

<li>✔ Predăm coletul curierului.</li>

<li>✔ Primești automat un nou e-mail când coletul este expediat.</li>

</ul>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<p style="font-size:16px;color:#555;line-height:28px;">

Îți mulțumim pentru încrederea acordată!

<br><br>

Dacă ai orice întrebare despre comandă, răspunde direct la acest e-mail și îți vom răspunde cât mai rapid.

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

Shopping made simple.

</p>

<p style="margin-top:8px;color:#d6e4ff;">

contact@novelions.ro

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