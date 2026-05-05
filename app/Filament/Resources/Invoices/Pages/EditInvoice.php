<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Invoices\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\Invoices\InvoiceResource;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Override;

final class EditInvoice extends EditRecord
{
    #[Override]
    protected static string $resource = InvoiceResource::class;

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->record;
        $data['line_items'] = $invoice->lines()
            ->orderBy('line_no')
            ->get()
            ->map(static function (InvoiceLine $line): array {
                return [
                    'sales_order_line_id' => $line->sales_order_line_id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_code_id' => $line->tax_code_id,
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

        /** @var Invoice $record */
        $record->update($data);
        $record->lines()->delete();

        foreach (array_values($line_items) as $index => $line) {
            $payload = Arr::only($line, [
                'sales_order_line_id',
                'description',
                'quantity',
                'unit_price',
                'tax_code_id',
            ]);
            $payload['line_no'] = $index + 1;
            $record->lines()->create($payload);
        }

        return $record;
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
