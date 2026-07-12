<x-filament-panels::page>
    <form wire:submit="generate">
        {{ $this->form }}
        <div class="mt-4 flex flex-wrap gap-2">
            <x-filament::button type="submit">
                Generate
            </x-filament::button>
            <x-filament::button type="button" color="gray" wire:click="exportCsv">
                Export CSV
            </x-filament::button>
        </div>
    </form>

    @if (! empty($report_data))
        <x-filament::section heading="Stock Valuation">
            <div class="mb-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div>
                    <span class="font-medium">Total quantity:</span>
                    {{ number_format((float) $report_data['total_quantity'], 2) }}
                </div>
                <div>
                    <span class="font-medium">Total value:</span>
                    {{ number_format((float) $report_data['total_value'], 2) }}
                </div>
            </div>

            @if (count($report_data['rows']) === 0)
                <div class="text-sm text-gray-500">No stock valuation rows for the selected filters.</div>
            @else
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
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
