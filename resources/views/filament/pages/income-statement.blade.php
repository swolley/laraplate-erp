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

    @if (!empty($report_data))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-filament::section heading="Revenue">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b font-medium">
                            <th class="text-left py-2">Code</th>
                            <th class="text-left py-2">Account</th>
                            <th class="text-right py-2">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report_data['revenue'] as $row)
                            <tr class="border-b">
                                <td class="py-1">{{ $row['account_code'] }}</td>
                                <td class="py-1">{{ $row['account_name'] }}</td>
                                <td class="py-1 text-right">{{ number_format((float) $row['balance'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 font-bold">
                            <td class="py-2" colspan="2">Total Revenue</td>
                            <td class="py-2 text-right">{{ number_format((float) $report_data['total_revenue'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </x-filament::section>

            <x-filament::section heading="Expenses">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b font-medium">
                            <th class="text-left py-2">Code</th>
                            <th class="text-left py-2">Account</th>
                            <th class="text-right py-2">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report_data['expenses'] as $row)
                            <tr class="border-b">
                                <td class="py-1">{{ $row['account_code'] }}</td>
                                <td class="py-1">{{ $row['account_name'] }}</td>
                                <td class="py-1 text-right">{{ number_format((float) $row['balance'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 font-bold">
                            <td class="py-2" colspan="2">Total Expenses</td>
                            <td class="py-2 text-right">{{ number_format((float) $report_data['total_expenses'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </x-filament::section>
        </div>

        <x-filament::section>
            <div class="text-center text-lg font-bold">
                Net Income: {{ number_format((float) $report_data['net_income'], 2) }}
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
