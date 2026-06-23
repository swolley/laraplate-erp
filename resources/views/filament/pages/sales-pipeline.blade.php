<x-filament-panels::page>
    <form wire:submit="generate">
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-4">
            Generate
        </x-filament::button>
    </form>

    @if (! empty($report_data))
        <x-filament::section heading="Pipeline Summary">
            <div class="mb-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                <div>
                    <span class="font-medium">Total opportunities:</span>
                    {{ $report_data['total_count'] }}
                </div>
                <div>
                    <span class="font-medium">Won:</span>
                    {{ $report_data['won_count'] }}
                </div>
                <div>
                    <span class="font-medium">Lost:</span>
                    {{ $report_data['lost_count'] }}
                </div>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b font-medium">
                        <th class="py-2 text-left">Status</th>
                        <th class="py-2 text-right">Count</th>
                        <th class="py-2 text-right">Expected Doc</th>
                        <th class="py-2 text-right">Expected Local</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report_data['by_status'] as $row)
                        <tr class="border-b">
                            <td class="py-1">{{ ucfirst($row['status']) }}</td>
                            <td class="py-1 text-right">{{ $row['count'] }}</td>
                            <td class="py-1 text-right">{{ number_format((float) $row['expected_value_doc'], 2) }}</td>
                            <td class="py-1 text-right">{{ number_format((float) $row['expected_value_local'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 font-bold">
                        <td class="py-2">Total</td>
                        <td class="py-2 text-right">{{ $report_data['total_count'] }}</td>
                        <td class="py-2 text-right">{{ number_format((float) $report_data['total_expected_value_doc'], 2) }}</td>
                        <td class="py-2 text-right">{{ number_format((float) $report_data['total_expected_value_local'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
