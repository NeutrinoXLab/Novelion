@extends('layouts.app')
@section('title', 'Date pentru restituire bancară')
@section('content')
<section class="max-w-2xl mx-auto px-6 py-16"><h1 class="text-3xl font-bold mb-5">Date pentru restituire bancară</h1>
<p class="mb-6">Pentru solicitarea #{{ $return->id }} din comanda {{ $return->order->order_number }}, poți comunica IBAN-ul și acordul pentru restituirea prin transfer bancar. Nu inițiem transferul doar prin completarea formularului; reclamația va fi soluționată separat.</p>
<form action="{{ route('returns.bank-details.store', $return) }}" method="POST" class="space-y-5">@csrf
<label class="block">IBAN <input name="bank_iban" value="{{ old('bank_iban', $return->bank_iban) }}" maxlength="34" required class="block w-full rounded-xl border-slate-300 mt-2"></label>
@error('bank_iban')<p class="text-red-600">{{ $message }}</p>@enderror
<label class="block"><input type="checkbox" name="accept_bank_transfer" value="1" required> Accept restituirea sumei datorate prin transfer bancar în contul indicat.</label>
@error('accept_bank_transfer')<p class="text-red-600">{{ $message }}</p>@enderror
<button class="bg-cyan-600 text-white rounded-xl px-6 py-3">Salvează datele</button></form></section>
@endsection
