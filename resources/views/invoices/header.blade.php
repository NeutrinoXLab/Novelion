<div class="header">

    <table>
        <tr>

            <td style="width: 70%; vertical-align: top;">

                <div class="invoice-title" style="text-align: left;">
                    FACTURĂ
                </div>

                <table class="invoice-meta">

                    <tr>
                        <td style="width: 80px;">
                            <strong>Număr:</strong>
                        </td>

                        <td>
                            {{ $order->order_number }}
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <strong>Data:</strong>
                        </td>

                        <td>
                            {{ $order->created_at->format('d.m.Y') }}
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <strong>Scadent la:</strong>
                        </td>

                        <td>
                            {{ $order->created_at->format('d.m.Y') }}
                        </td>
                    </tr>

                </table>

            </td>

            <td style="width: 30%; text-align: right; vertical-align: top;">

                <strong></strong>

            </td>

        </tr>
    </table>

</div>