<div class="header">

    <div class="left box">

        <div class="company-name">
            NOVELION S.R.L.
        </div>

        <br>

        Str. Daciei nr. 11<br>
        Ploiești, Prahova<br>
        Cod poștal: 100352

        <br><br>

        <strong>CUI:</strong> 52627291<br>
        <strong>Reg. Comerțului:</strong> J2025075714007<br>

        <strong>IBAN:</strong><br>
        RO85BTRLRONCRT0DA9291801

        <br>

        <strong>Banca:</strong>
        Banca Transilvania

        <br>

        <strong>Telefon:</strong>
        0750 444 672

        <br>

        <strong>Email:</strong>
        novelionprime@gmail.com

    </div>

    <div class="right box">

        <div class="invoice-title">
            FACTURĂ FISCALĂ
        </div>

        <table class="invoice-meta">

            <tr>
                <td>
                    <strong>Factura:</strong>
                </td>

                <td class="text-right">
                    {{ $order->order_number }}
                </td>
            </tr>

            <tr>
                <td>
                    <strong>Data:</strong>
                </td>

                <td class="text-right">
                    {{ $order->created_at->format('d.m.Y') }}
                </td>
            </tr>

            <tr>
                <td>
                    <strong>Ora:</strong>
                </td>

                <td class="text-right">
                    {{ $order->created_at->format('H:i') }}
                </td>
            </tr>

            <tr>
                <td>
                    <strong>Plată:</strong>
                </td>

                <td class="text-right">

                    {{ $order->payment_method === 'cash'
                        ? 'Ramburs'
                        : 'Card bancar' }}

                </td>
            </tr>

        </table>

    </div>

    <div class="clear"></div>

</div>