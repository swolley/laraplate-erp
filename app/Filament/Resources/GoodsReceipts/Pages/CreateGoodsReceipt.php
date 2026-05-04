<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\GoodsReceipts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Services\Inventory\GoodsReceiptInventoryService;
use Override;

final class CreateGoodsReceipt extends CreateRecord
{
    #[Override]
    protected static string $resource = GoodsReceiptResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $line_items = $data['line_items'] ?? [];
        unset($data['line_items']);

        /** @var GoodsReceipt $record */
        $record = GoodsReceipt::query()->create($data);

        foreach (array_values($line_items) as $line) {
            $payload = Arr::only($line, [
                'item_id',
                'warehouse_id',
                'quantity',
                'unit_cost',
                'purchase_order_line_id',
            ]);
            $payload['company_id'] = $record->company_id;
            $record->lines()->create($payload);
        }

        $record->refresh();

        if ($record->posted_at !== null && $record->inventory_posted_at === null) {
            app(GoodsReceiptInventoryService::class)->postInventory($record);
            $record->saveQuietly();
        }

        return $record;
    }
}
