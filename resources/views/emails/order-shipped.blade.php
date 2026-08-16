<!DOCTYPE html>
<html lang="ro">

<head>
    <meta charset="UTF-8">
    <title>Comanda a fost expediată</title>
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

📦 Comanda ta a fost expediată

</p>

<p style="margin-top:8px;font-size:15px;color:#cbd5e1;">

Coletul tău este deja în drum spre tine.

</p>

</td>

</tr>

<!-- CONTENT -->

<tr>

<td style="padding:45px;">

<h2 style="margin-top:0;color:#0f172a;">

Salut,

<strong>

{{ $order->first_name }} {{ $order->last_name }}

</strong>

👋

</h2>

<p style="font-size:17px;color:#555;line-height:30px;">

Avem vești bune!

<br><br>

Comanda

<strong>{{ $order->order_number }}</strong>

a fost predată curierului și urmează să fie livrată în cel mai scurt timp.

</p>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<h3 style="color:#0f172a;">

Produse expediate

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

<h3 style="color:#0f172a;">

Adresa de livrare

</h3>

<p style="line-height:30px;">

<strong>

{{ $order->first_name }} {{ $order->last_name }}

</strong>

<br>

{{ $order->address }}

<br>

{{ $order->postal_code }} {{ $order->city }}

<br>

{{ $order->county }}

</p>

<hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

<p style="font-size:18px;">

Status comandă:

<strong style="color:#2563eb;">

🚚 Expediată

</strong>

</p>

<p style="font-size:16px;color:#555;line-height:28px;">

În curând vei primi coletul.

Îți mulțumim că ai ales Novelion!

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