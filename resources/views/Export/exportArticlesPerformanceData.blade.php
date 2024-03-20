<table>
    <thead>
        <tr>
            <th>MPN</th>
            <th>SKU</th>
            <th>gtin</th>
            <th>Product name</th>
            <th>Units sold</th>
            <th>Units returned</th>
            <th>Return rate </th>
            {{-- <th>Lifetime Return rate </th> --}}
            <th>Revenue after returns </th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalSales = 0;
            $totalReturnsQty = 0;
            $totalTurnover = 0;
            $totalLifetimeSales = 0;
            $totalLifetimeReturnsQty = 0;
        @endphp
        @foreach ($articleFilterData as $articleData)
            @php
                $totalSales += $articleData['sales'];
                $totalReturnsQty += $articleData['returns'];
                $totalTurnover += $articleData['turnover'];
                // $totalLifetimeSales += $articleData['lifetime_sales'];
                // $totalLifetimeReturnsQty += $articleData['lifetime_returns'];
            @endphp
            <tr>
                <td>{{ $articleData['MPN'] }}</td>
                <td>{{ $articleData['SKU'] }}</td>
                <td>{{ $articleData['gtin'] }}</td>
                <td>{{ $articleData['name'] }}</td>
                <td>{{ $articleData['sales'] }}</td>
                <td>{{ $articleData['returns'] }}</td>
                <td>
                    @if ($articleData['return_rate'] > 100 || ($articleData['sales'] == 0 && $articleData['returns'] > 0))
                        {!! '> 100 %' !!}
                    @else
                        {{ number_format($articleData['return_rate'], 2, ',', '.') }}
                    @endif
                </td>
                {{-- <td>
                    {{ number_format($articleData['lifetime_return_rate'], 2, ',', '.') }}
                </td> --}}
                <td>
                    {{ number_format($articleData['turnover'], 2, ',', '.') }}
                </td>
            </tr>
        @endforeach
        @php
            $totalReturnRate = $totalSales > 0 ? ($totalReturnsQty / $totalSales) * 100 : 0;
            $formattedTotalReturnRate = number_format($totalReturnRate, 2, ',', '.');

            // $totalLifetimeReturnRate = $totalLifetimeSales > 0 ? ($totalLifetimeReturnsQty / $totalLifetimeSales) * 100 : 0;
            // $formattedTotalLifetimeReturnRate = number_format($totalLifetimeReturnRate, 2, ',', '.');
        @endphp
    </tbody>
    <tfoot>
        <tr>
            <th></th>
            <th></th>
            <th></th>
            <th>Gesamtergebnis</th>
            <th>{{ $totalSales }}</th>
            <th>{{ $totalReturnsQty }}</th>
            <th>{{ $formattedTotalReturnRate }} %</th>
            {{-- <th>{{ $formattedTotalLifetimeReturnRate }} %</th> --}}
            <th>{{ number_format($totalTurnover, 2, ',', '.') }} €</th>
        </tr>
    </tfoot>
</table>
