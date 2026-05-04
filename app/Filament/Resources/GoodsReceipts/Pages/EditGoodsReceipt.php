<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\GoodsReceipts\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Models\GoodsReceiptLine;
use Override;

final class EditGoodsReceipt extends EditRecord
{
    #[Override]
    protected static string $resource = GoodsReceiptResource::class;

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
        /** @var GoodsReceipt $goods_receipt */
        $goods_receipt = $this->record;
        $data['line_items'] = $goods_receipt->lines()
            ->orderBy('id')
            ->get()
            ->map(static function (GoodsReceiptLine $line): array {
                return [
                    'item_id' => $line->item_id,
                    'warehouse_id' => $line->warehouse_id,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_cost,
                    'purchase_order_line_id' => $line->purchase_order_line_id,
                ];
            })
            ->all();

        return $data;
    }

    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var GoodsReceipt $record */
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
                'unit_cost',
                'purchase_order_line_id',
            ]);
            $payload['company_id'] = $record->company_id;
            $record->lines()->create($payload);
        }

        $record->update($data);

        return $record;
    }
}
