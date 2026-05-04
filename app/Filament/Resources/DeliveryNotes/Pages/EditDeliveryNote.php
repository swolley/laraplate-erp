<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DeliveryNotes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Override;

final class EditDeliveryNote extends EditRecord
{
    #[Override]
    protected static string $resource = DeliveryNoteResource::class;

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
        /** @var DeliveryNote $delivery_note */
        $delivery_note = $this->record;
        $data['line_items'] = $delivery_note->lines()
            ->orderBy('id')
            ->get()
            ->map(static function (DeliveryNoteLine $line): array {
                return [
                    'item_id' => $line->item_id,
                    'warehouse_id' => $line->warehouse_id,
                    'quantity' => $line->quantity,
                    'sales_order_line_id' => $line->sales_order_line_id,
                ];
            })
            ->all();

        return $data;
    }

    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var DeliveryNote $record */
        if ($record->inventory_posted_at !== null) {
            unset($data['line_items']);
            $record->update($data);

            return $record;
        }

        $line_items = $data['line_items'] ?? [];
        unset($data['line_items']);

        $record->lines()->delete();

        foreach (array_values($line_items) as $line) {
            $payload = Arr::only($line, [
                'item_id',
                'warehouse_id',
                'quantity',
                'sales_order_line_id',
            ]);
            $payload['company_id'] = $record->company_id;
            $record->lines()->create($payload);
        }

        $record->update($data);

        return $record;
    }
}
