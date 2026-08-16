<table class="products">

    <thead>

        <tr>

            <th width="5%" class="text-center">
                Nr.
            </th>

            <th width="40%">
                Denumire produs
            </th>

            <th width="10%" class="text-center">
                U.M.
            </th>

            <th width="10%" class="text-center">
                Cant.
            </th>

            <th width="15%" class="text-right">
                Preț unitar
            </th>

            <th width="20%" class="text-right">
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
                    <strong>
                        {{ $item->product_name }}
                    </strong>
                </td>

                <td class="text-center">
                    buc.
                </td>

                <td class="text-center">
                    {{ $item->quantity }}
                </td>

                <td class="text-right">
                    {{ number_format($item->price, 2, ',', '.') }}
                    RON
                </td>

                <td class="text-right">
                    <strong>
                        {{ number_format($item->total, 2, ',', '.') }}
                        RON
                    </strong>
                </td>

            </tr>

        @endforeach

    </tbody>

</table>