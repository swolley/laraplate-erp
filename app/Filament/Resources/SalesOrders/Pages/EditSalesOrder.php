<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SalesOrders\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\SalesOrders\SalesOrderResource;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Override;

final class EditSalesOrder extends EditRecord
{
    #[Override]
    protected static string $resource = SalesOrderResource::class;

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var SalesOrder $sales_order */
        $sales_order = $this->record;
        $data['line_items'] = $sales_order->lines()
            ->orderBy('id')
            ->get()
            ->map(static function (SalesOrderLine $line): array {
                return [
                    'name' => $line->name,
                    'qty_ordered' => $line->qty_ordered,
                    'qty_delivered' => $line->qty_delivered,
                    'qty_invoiced' => $line->qty_invoiced,
                    'unit_price' => $line->unit_price,
                    'status' => $line->status->value,
                ];
            })
            ->all();

        return $data;
    }

    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $line_items = $data['line_items'] ?? [];
        unset($data['line_items']);

        /** @var SalesOrder $record */
        $record->update($data);

        $record->lines()->delete();

        foreach (array_values($line_items) as $line) {
            $payload = Arr::only($line, [
                'name',
                'qty_ordered',
                'qty_delivered',
                'qty_invoiced',
                'unit_price',
                'status',
            ]);
            $record->lines()->create($payload);
        }

        return $record;
    }
}
