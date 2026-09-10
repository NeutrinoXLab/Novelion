<table class="products">

    <thead>
        <tr>

            <th width="6%" class="text-center">
                Nr. crt.
            </th>

            <th width="44%">
                Denumire produse/servicii
            </th>

            <th width="10%" class="text-center">
                U.M.
            </th>

            <th width="10%" class="text-center">
                Cantitate
            </th>

            <th width="15%" class="text-right">
                Preț unitar
            </th>

            <th width="15%" class="text-right">
                Valoare
            </th>

        </tr>
    </thead>

    <tbody>

        @foreach($order->items as $index => $item)

            <tr>

                <td class="text-center">
                    {{ $index + 1 }}
                </td>

                <td>
                    {{ $item->product_name }}
                </td>

                <td class="text-center">
                    buc.
                </td>

                <td class="text-center">
                    {{ number_format($item->quantity, 3, '.', '') }}
                </td>

                <td class="text-right">
                    {{ number_format($item->price, 2, ',', '.') }} RON
                </td>

                <td class="text-right">
                    {{ number_format($item->total, 2, ',', '.') }} RON
                </td>

            </tr>

        @endforeach

    </tbody>

</table>