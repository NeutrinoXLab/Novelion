<table class="customer-table">

    <tr>

        {{-- FURNIZOR --}}
        <td class="customer-box">

            <div class="section-title">
                Furnizor
            </div>

            <strong>NOVELION S.R.L.</strong>

            <br><br>

            Ploiești, Prahova

            <br>

            CUI: 52627291

        </td>


        <td width="4%"></td>


        {{-- CLIENT --}}
        <td class="customer-box">

            <div class="section-title">
                Client
            </div>

            @if($order->customer_type === 'company')

                <strong>
                    {{ $order->company_name }}
                </strong>

                <br><br>

                <strong>CUI:</strong>
                {{ $order->company_vat }}

                <br>

                <strong>Reg. Comerțului:</strong>
                {{ $order->company_registration }}

                <br><br>

                {{ $order->company_address ?? '' }}

                <br>

                {{ $order->company_postal_code ?? '' }}
                {{ $order->company_city ?? '' }}

                <br>

                {{ $order->company_county ?? '' }}

            @else

                <strong>
                    {{ $order->first_name }}
                    {{ $order->last_name }}
                </strong>

                <br><br>

                {{ $order->address }}

                <br>

                {{ $order->postal_code }}
                {{ $order->city }}

                <br>

                {{ $order->county }}

            @endif

            <br><br>

            <strong>Email:</strong>
            {{ $order->email }}

            <br>

            <strong>Telefon:</strong>
            {{ $order->phone }}

        </td>

    </tr>

</table>