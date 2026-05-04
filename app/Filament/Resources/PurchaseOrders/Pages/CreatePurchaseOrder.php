<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PurchaseOrders\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;
use Override;

final class CreatePurchaseOrder extends CreateRecord
{
    #[Override]
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $line_items = $data['line_items'] ?? [];
        unset($data['line_items']);

        if (blank($data['reference'] ?? null)) {
            $company = Company::query()->findOrFail((int) $data['company_id']);
            $data['reference'] = app(DocumentNumberAllocator::class)
                ->next($company, DocumentType::PurchaseOrder, 0);
        }

        /** @var PurchaseOrder $record */
        $record = PurchaseOrder::query()->create($data);

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

        return $record;
    }
}
