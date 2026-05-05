<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\Invoices\InvoiceResource;
use Modules\ERP\Models\Invoice;
use Override;

final class CreateInvoice extends CreateRecord
{
    #[Override]
    protected static string $resource = InvoiceResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $line_items = $data['line_items'] ?? [];
        unset($data['line_items']);

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->create($data);

        foreach (array_values($line_items) as $index => $line) {
            $payload = Arr::only($line, [
                'sales_order_line_id',
                'description',
                'quantity',
                'unit_price',
                'tax_code_id',
            ]);
            $payload['line_no'] = $index + 1;
            $invoice->lines()->create($payload);
        }

        return $invoice;
    }
}
