<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SalesOrders\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Filament\Resources\SalesOrders\SalesOrderResource;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;
use Override;

final class CreateSalesOrder extends CreateRecord
{
    #[Override]
    protected static string $resource = SalesOrderResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $line_items = $data['line_items'] ?? [];
        unset($data['line_items']);

        if (blank($data['reference'] ?? null)) {
            $company = Company::query()->findOrFail((int) $data['company_id']);
            $data['reference'] = app(DocumentNumberAllocator::class)
                ->next($company, DocumentType::SalesOrder, 0);
        }

        /** @var SalesOrder $record */
        $record = SalesOrder::query()->create($data);

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
