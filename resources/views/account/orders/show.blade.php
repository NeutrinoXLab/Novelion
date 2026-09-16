@extends('layouts.app')

@section('title', 'Detalii comandă')

@section('content')

<section class="bg-slate-100 py-16 min-h-screen">

    <div class="max-w-6xl mx-auto px-6">

        {{-- HEADER --}}
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-6 mb-10">

            <div>

                <h1 class="text-4xl lg:text-5xl font-bold text-slate-900">
                    Comanda {{ $order->order_number }}
                </h1>

                <p class="text-slate-500 mt-2">
                    Plasată la {{ $order->created_at->format('d.m.Y H:i') }}
                </p>

            </div>

            <a
                href="{{ route('my-orders.index') }}"
                class="inline-block bg-white border border-slate-200 px-6 py-3 rounded-xl hover:bg-slate-50 transition text-center">

                ← Înapoi la comenzile mele

            </a>

        </div>


        {{-- STATUS COMANDĂ --}}
        @php

            $statuses = [

                'pending' => [
                    'label' => 'În așteptare',
                    'class' => 'bg-yellow-100 text-yellow-700',
                ],

                'processing' => [
                    'label' => 'În procesare',
                    'class' => 'bg-blue-100 text-blue-700',
                ],

                'shipped' => [
                    'label' => 'Expediată',
                    'class' => 'bg-purple-100 text-purple-700',
                ],

                'delivered' => [
                    'label' => 'Livrată',
                    'class' => 'bg-emerald-100 text-emerald-700',
                ],

                'cancelled' => [
                    'label' => 'Anulată',
                    'class' => 'bg-red-100 text-red-700',
                ],

            ];

            $currentStatus = $statuses[$order->status] ?? [
                'label' => $order->status,
                'class' => 'bg-gray-100 text-gray-700',
            ];


            $paymentStatuses = [

                'pending' => [
                    'label' => 'În așteptare',
                    'class' => 'bg-yellow-100 text-yellow-700',
                ],

                'paid' => [
                    'label' => 'Plătită',
                    'class' => 'bg-green-100 text-green-700',
                ],

                'failed' => [
                    'label' => 'Eșuată',
                    'class' => 'bg-red-100 text-red-700',
                ],

                'refunded' => [
                    'label' => 'Rambursată',
                    'class' => 'bg-purple-100 text-purple-700',
                ],

            ];

            $currentPaymentStatus = $paymentStatuses[$order->payment_status] ?? [
                'label' => $order->payment_status,
                'class' => 'bg-gray-100 text-gray-700',
            ];


            /*
             * Eligibilitate retur
             */
            $returnDeadline = $order->delivered_at
                ? app(\App\Services\WithdrawalDeadline::class)->forDelivery($order->delivered_at)
                : null;

            $existingReturnRequest = $order->returnRequests()
                ->whereIn('status', [
                    'requested',
                    'approved',
                    'received',
                ])
                ->latest('requested_at')
                ->first();

            $canRequestReturn =
                $order->status === 'delivered' &&
                $order->delivered_at &&
                $returnDeadline;

        @endphp


        <div class="grid lg:grid-cols-3 gap-8">


            {{-- COLOANA PRINCIPALĂ --}}
            <div class="lg:col-span-2 space-y-8">


                {{-- PRODUSE --}}
                <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                    <h2 class="text-2xl font-bold text-slate-900 mb-8">
                        Produse comandate
                    </h2>

                    <div class="space-y-6">

                        @foreach($order->items as $item)

                            <div class="flex flex-col sm:flex-row sm:justify-between gap-4 border-b border-slate-200 pb-5 last:border-b-0 last:pb-0">

                                <div>

                                    <div class="font-semibold text-lg text-slate-900">
                                        {{ $item->product_name }}
                                    </div>

                                    <div class="text-slate-500 mt-1">
                                        Cantitate: {{ $item->quantity }}
                                    </div>

                                    <div class="text-sm text-slate-400 mt-1">
                                        Preț unitar:
                                        {{ number_format($item->price, 2, ',', '.') }} Lei
                                    </div>

                                </div>

                                <div class="font-bold text-cyan-600 text-lg sm:text-right">

                                    {{ number_format($item->total, 2, ',', '.') }} Lei

                                </div>

                            </div>

                        @endforeach

                    </div>

                </div>


                {{-- ADRESA LIVRARE --}}
                <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                    <h2 class="text-2xl font-bold text-slate-900 mb-6">
                        Adresa de livrare
                    </h2>

                    <div class="space-y-1 text-slate-700">

                        <p class="font-semibold">
                            {{ $order->shipping_first_name ?: $order->first_name }}
                            {{ $order->shipping_last_name ?: $order->last_name }}
                        </p>

                        <p>
                            {{ $order->shipping_address ?: $order->address }}
                        </p>

                        <p>
                            {{ $order->shipping_city ?: $order->city }},
                            {{ $order->shipping_county ?: $order->county }}
                        </p>

                        @if($order->shipping_postal_code ?: $order->postal_code)

                            <p>
                                {{ $order->shipping_postal_code ?: $order->postal_code }}
                            </p>

                        @endif

                        <p class="mt-4">
                            {{ $order->shipping_phone ?: $order->phone }}
                        </p>

                    </div>

                </div>


                {{-- OBSERVAȚII --}}
                @if($order->notes)

                    <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                        <h2 class="text-2xl font-bold text-slate-900 mb-4">
                            Observații
                        </h2>

                        <p class="text-slate-600 whitespace-pre-line">
                            {{ $order->notes }}
                        </p>

                    </div>

                @endif

                @foreach($order->returnRequests as $return)
                    @if($return->refund_method === 'bank_transfer' && ! $return->bank_transfer_accepted_at && $return->status !== 'rejected')
                        <a href="{{ route('returns.bank-details', $return) }}" class="block rounded-xl bg-cyan-50 p-4 text-cyan-900 underline">Comunică IBAN-ul și acordul pentru eventualul transfer bancar aferent solicitării #{{ $return->id }}</a>
                    @endif
                @endforeach

            </div>


            {{-- COLOANA LATERALĂ --}}
            <div class="space-y-8">


                {{-- STATUS --}}
                <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                    <h2 class="text-2xl font-bold text-slate-900 mb-6">
                        Status comandă
                    </h2>

                    <span
                        class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold {{ $currentStatus['class'] }}">

                        {{ $currentStatus['label'] }}

                    </span>

                    @if($order->payment_method === 'stripe' && $order->payment_status === 'pending')
                        <form method="POST" action="{{ route('checkout.cancel-order', $order) }}" class="mt-6">
                            @csrf
                            <button type="submit" class="w-full border border-red-300 text-red-700 py-3 rounded-xl font-semibold hover:bg-red-50">
                                Anulează comanda neplătită
                            </button>
                        </form>
                    @endif

                </div>


                {{-- RETUR --}}
                @if($canRequestReturn)

                    <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                        <h2 class="text-2xl font-bold text-slate-900 mb-4">
                            Vreau să returnez un produs / Produs defect/neconform
                        </h2>

                        <p class="text-slate-600 text-sm leading-6 mb-5">
                            Pentru retragere ai 14 zile; reclamația pentru neconformitate este un flux separat.
                        </p>

                        <p class="text-slate-500 text-sm mb-5">
                            @if(now()->timezone('Europe/Bucharest')->lessThanOrEqualTo($returnDeadline))
                                Termenul de retragere expiră la <strong>{{ $returnDeadline->format('d.m.Y H:i') }}</strong>.
                            @else
                                Termenul de retragere a expirat. Poți în continuare raporta un produs defect/neconform.
                            @endif
                        </p>

                        <a
                            href="{{ route('returns.create', $order) }}"
                            class="block w-full bg-cyan-500 hover:bg-cyan-600 text-white py-3 rounded-xl font-semibold text-center transition">

                            Alege produsul și cantitatea

                        </a>

                    </div>

                @elseif($existingReturnRequest)

                    <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                        <h2 class="text-2xl font-bold text-slate-900 mb-4">
                            Retur produs
                        </h2>

                        <p class="text-slate-600 text-sm leading-6 mb-4">
                            Pentru această comandă există deja o solicitare de retur.
                        </p>

                        <div class="inline-flex items-center px-3 py-2 rounded-full bg-yellow-100 text-yellow-700 text-sm font-semibold">
                            Solicitare în procesare
                        </div>

                    </div>

                @endif


                {{-- PLATĂ --}}
                <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                    <h2 class="text-2xl font-bold text-slate-900 mb-6">
                        Plată
                    </h2>

                    <div class="space-y-4">

                        <div class="flex justify-between gap-4">

                            <span class="text-slate-500">
                                Metodă
                            </span>

                            <span class="font-semibold text-right">

                                {{ $order->payment_method === 'cash'
                                    ? 'Ramburs'
                                    : 'Card bancar'
                                }}

                            </span>

                        </div>

                        <div class="flex justify-between gap-4">

                            <span class="text-slate-500">
                                Status
                            </span>

                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold {{ $currentPaymentStatus['class'] }}">

                                {{ $currentPaymentStatus['label'] }}

                            </span>

                        </div>

                    </div>

                </div>


                {{-- REZUMAT --}}
                <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                    <h2 class="text-2xl font-bold text-slate-900 mb-6">
                        Rezumat comandă
                    </h2>

                    <div class="space-y-4">

                        <div class="flex justify-between gap-4">

                            <span class="text-slate-500">
                                Subtotal
                            </span>

                            <span class="font-semibold">
                                {{ number_format($order->subtotal, 2, ',', '.') }} Lei
                            </span>

                        </div>

                        <div class="flex justify-between gap-4">

                            <span class="text-slate-500">
                                Transport
                            </span>

                            <span class="font-semibold">

                                @if($order->shipping_cost > 0)

                                    {{ number_format($order->shipping_cost, 2, ',', '.') }} Lei

                                @else

                                    Gratuit

                                @endif

                            </span>

                        </div>

                        <hr>

                        <div class="flex justify-between gap-4 items-center">

                            <span class="text-xl font-bold">
                                Total
                            </span>

                            <span class="text-2xl font-bold text-cyan-600 text-right">

                                {{ number_format($order->total, 2, ',', '.') }} Lei

                            </span>

                        </div>

                    </div>

                </div>


                {{-- LIVRARE --}}
                @if($order->courier || $order->awb_number || $order->tracking_url || $order->shipped_at)

                    <div class="bg-white rounded-3xl shadow-sm p-6 lg:p-8">

                        <h2 class="text-2xl font-bold text-slate-900 mb-6">
                            Livrare
                        </h2>

                        <div class="space-y-4">

                            @if($order->courier)

                                <div class="flex justify-between gap-4">

                                    <span class="text-slate-500">
                                        Curier
                                    </span>

                                    <span class="font-semibold text-right">
                                        {{ $order->courier }}
                                    </span>

                                </div>

                            @endif

                            @if($order->awb_number)

                                <div class="flex justify-between gap-4">

                                    <span class="text-slate-500">
                                        AWB
                                    </span>

                                    <span class="font-semibold text-right">
                                        {{ $order->awb_number }}
                                    </span>

                                </div>

                            @endif

                            @if($order->tracking_url)

                                <div>

                                    <a
                                        href="{{ $order->tracking_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block w-full bg-cyan-500 hover:bg-cyan-600 text-white py-3 rounded-xl font-semibold text-center transition">

                                        Urmărește coletul

                                    </a>

                                </div>

                            @endif

                            @if($order->shipped_at)

                                <div class="text-sm text-slate-500">

                                    Expediată la:
                                    {{ $order->shipped_at->format('d.m.Y H:i') }}

                                </div>

                            @endif

                        </div>

                    </div>

                @endif


            </div>

        </div>

    </div>

</section>

@endsection
