<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\ERP\Filament\Resources\Invoices\InvoiceResource;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
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
                'purchase_order_line_id',
                'goods_receipt_line_id',
                'description',
                'quantity',
                'unit_price',
                'tax_code_id',
            ]);
            $payload['line_no'] = $index + 1;

            /** @var InvoiceLine $invoice_line */
            $invoice_line = $invoice->lines()->create($payload);
            $this->syncDeliveryNoteLineLinks($invoice_line, $line);
        }

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $line_data
     */
    private function syncDeliveryNoteLineLinks(InvoiceLine $invoice_line, array $line_data): void
    {
        $links = $line_data['delivery_note_line_links'] ?? [];
        $pivot = [];

        foreach ($links as $link) {
            $delivery_note_line_id = $link['delivery_note_line_id'] ?? null;

            if ($delivery_note_line_id === null || $delivery_note_line_id === '') {
                continue;
            }

            $pivot[(int) $delivery_note_line_id] = ['quantity' => number_format((float) $link['quantity'], 4, '.', '')];
        }

        $invoice_line->delivery_note_lines()->sync($pivot);
    }
}
