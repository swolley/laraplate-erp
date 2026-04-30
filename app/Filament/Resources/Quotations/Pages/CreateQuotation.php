<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Quotations\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\Quotations\QuotationResource;
use Modules\ERP\Models\Quotation;
use Override;

final class CreateQuotation extends CreateRecord
{
    #[Override]
    protected static string $resource = QuotationResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $line_items = $data['line_items'] ?? [];
        unset($data['line_items']);

        /** @var Quotation $record */
        $record = Quotation::query()->create($data);

        foreach (array_values($line_items) as $line) {
            $payload = Arr::only($line, ['name', 'billing_mode', 'quantity', 'unit_price', 'price_list_item_id']);
            $record->quotation_items()->create($payload);
        }

        return $record;
    }
}
