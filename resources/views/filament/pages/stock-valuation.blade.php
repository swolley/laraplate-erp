<x-filament-panels::page>
    <form wire:submit="generate">
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-4">
            Generate
        </x-filament::button>
    </form>

    @if (! empty($report_data))
        <x-filament::section heading="Stock Valuation">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b font-medium">
                        <th class="py-2 text-left">SKU</th>
                        <th class="py-2 text-left">Item</th>
                        <th class="py-2 text-left">Warehouse</th>
                        <th class="py-2 text-right">Quantity</th>
                        <th class="py-2 text-right">Avg Cost</th>
                        <th class="py-2 text-right">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report_data['rows'] as $row)
                        <tr class="border-b">
                            <td class="py-1">{{ $row['sku'] }}</td>
                            <td class="py-1">{{ $row['item_name'] }}</td>
                            <td class="py-1">{{ $row['warehouse_code'] }} - {{ $row['warehouse_name'] }}</td>
                            <td class="py-1 text-right">{{ number_format((float) $row['quantity'], 2) }}</td>
                            <td class="py-1 text-right">{{ number_format((float) $row['weighted_avg_cost'], 2) }}</td>
                            <td class="py-1 text-right">{{ number_format((float) $row['value'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 font-bold">
                        <td class="py-2" colspan="3">Total</td>
                        <td class="py-2 text-right">{{ number_format((float) $report_data['total_quantity'], 2) }}</td>
                        <td class="py-2"></td>
                        <td class="py-2 text-right">{{ number_format((float) $report_data['total_value'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
