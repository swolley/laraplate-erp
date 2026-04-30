<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Quotations\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\Quotations\QuotationResource;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\QuotationItem;
use Override;

final class EditQuotation extends EditRecord
{
    #[Override]
    protected static string $resource = QuotationResource::class;

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Quotation $quotation */
        $quotation = $this->record;
        $data['line_items'] = $quotation->quotation_items()
            ->orderBy('id')
            ->get()
            ->map(static function (QuotationItem $item): array {
                return [
                    'name' => $item->name,
                    'billing_mode' => $item->billing_mode->value,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'price_list_item_id' => $item->price_list_item_id,
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

        /** @var Quotation $record */
        $record->update($data);

        $record->quotation_items()->delete();

        foreach (array_values($line_items) as $line) {
            $payload = Arr::only($line, ['name', 'billing_mode', 'quantity', 'unit_price', 'price_list_item_id']);
            $record->quotation_items()->create($payload);
        }

        return $record;
    }
}
