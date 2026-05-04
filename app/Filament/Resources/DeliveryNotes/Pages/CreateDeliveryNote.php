<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DeliveryNotes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Services\Inventory\DeliveryNoteInventoryService;
use Override;

final class CreateDeliveryNote extends CreateRecord
{
    #[Override]
    protected static string $resource = DeliveryNoteResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $line_items = $data['line_items'] ?? [];
        unset($data['line_items']);

        /** @var DeliveryNote $record */
        $record = DeliveryNote::query()->create($data);

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

        $record->refresh();

        if ($record->posted_at !== null && $record->inventory_posted_at === null) {
            app(DeliveryNoteInventoryService::class)->postInventory($record);
            $record->saveQuietly();
        }

        return $record;
    }
}
