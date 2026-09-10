<table style="width: 100%; margin-top: 10px;">

    <tr>

        {{-- EMIS DE --}}
        <td style="width: 50%; vertical-align: top;">

            <div class="issuer">

                <div class="issuer-title">
                    Emis de
                </div>

                <strong>
                    POPESCU ROXANA
                </strong>

                <br>

                CI: PX 992009

            </div>

        </td>


        {{-- TOTALURI --}}
        <td style="width: 50%; vertical-align: top;">

            <table class="totals">

                <tr>
                    <td>
                        Subtotal
                    </td>

                    <td class="text-right">
                        {{ number_format($order->subtotal, 2, ',', '.') }} RON
                    </td>
                </tr>


                @if($order->shipping_cost > 0)

                    <tr>
                        <td>
                            Transport
                        </td>

                        <td class="text-right">
                            {{ number_format($order->shipping_cost, 2, ',', '.') }} RON
                        </td>
                    </tr>

                @endif


                <tr class="total-final">

                    <td>
                        TOTAL
                    </td>

                    <td class="text-right">
                        {{ number_format($order->total, 2, ',', '.') }} RON
                    </td>

                </tr>

            </table>

        </td>

    </tr>

</table>