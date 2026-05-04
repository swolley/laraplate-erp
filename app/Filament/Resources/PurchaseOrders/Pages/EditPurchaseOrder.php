<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PurchaseOrders\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Override;

final class EditPurchaseOrder extends EditRecord
{
    #[Override]
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var PurchaseOrder $purchase_order */
        $purchase_order = $this->record;
        $data['line_items'] = $purchase_order->lines()
            ->orderBy('id')
            ->get()
            ->map(static function (PurchaseOrderLine $line): array {
                return [
                    'item_id' => $line->item_id,
                    'name' => $line->name,
                    'qty_ordered' => $line->qty_ordered,
                    'qty_received' => $line->qty_received,
                    'unit_price' => $line->unit_price,
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

        /** @var PurchaseOrder $record */
        $record->lines()->delete();

        foreach (array_values($line_items) as $line) {
            $payload = Arr::only($line, [
                'item_id',
                'name',
                'qty_ordered',
                'qty_received',
                'unit_price',
            ]);
            $record->lines()->create($payload);
        }

        $record->update($data);

        return $record;
    }
}
