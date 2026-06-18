<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\ReturnOrderLine;
use Modules\ERP\Services\Accounting\CreditNoteService;

final readonly class ReturnOrderService
{
    public function __construct(
        private CustomerReturnReceiptService $receipt_service,
        private CreditNoteService $credit_note_service,
    ) {}

    public function approve(ReturnOrder $return_order): ReturnOrder
    {
        return DB::transaction(function () use ($return_order): ReturnOrder {
            /** @var ReturnOrder $locked */
            $locked = ReturnOrder::query()->lockForUpdate()->findOrFail((int) $return_order->id);

            if ($locked->status !== ReturnStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => ['Customer return can only be approved from draft.'],
                ]);
            }

            $this->assertCustomerParty($locked);

            $locked->status = ReturnStatus::Approved;
            $locked->save();

            return $locked;
        });
    }

    public function complete(ReturnOrder $return_order): ReturnOrder
    {
        return $this->receipt_service->receive($return_order);
    }

    public function createCreditNote(ReturnOrder $return_order): Invoice
    {
        return DB::transaction(function () use ($return_order): Invoice {
            /** @var ReturnOrder $locked */
            $locked = ReturnOrder::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail((int) $return_order->id);

            if ($locked->status !== ReturnStatus::Processed) {
                throw ValidationException::withMessages([
                    'status' => ['Customer return must be processed before creating a credit note.'],
                ]);
            }

            if ($locked->credit_note_invoice_id !== null) {
                throw ValidationException::withMessages([
                    'credit_note_invoice_id' => ['This customer return already has a linked credit note.'],
                ]);
            }

            if ($locked->invoice_id === null) {
                throw ValidationException::withMessages([
                    'invoice_id' => ['Customer return requires a source invoice before creating a credit note.'],
                ]);
            }

            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->whereKey((int) $locked->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $invoice->company_id !== (int) $locked->company_id) {
                throw ValidationException::withMessages([
                    'invoice_id' => ['The source invoice must belong to the same company as the customer return.'],
                ]);
            }

            $line_overrides = $this->creditNoteLineOverrides($locked, $invoice);
            $credit_note = $this->credit_note_service->createFromInvoice($invoice, $line_overrides);

            $locked->credit_note_invoice_id = (int) $credit_note->getKey();
            $locked->save();

            return $credit_note;
        });
    }

    public function cancel(ReturnOrder $return_order): ReturnOrder
    {
        return DB::transaction(function () use ($return_order): ReturnOrder {
            /** @var ReturnOrder $locked */
            $locked = ReturnOrder::query()->lockForUpdate()->findOrFail((int) $return_order->id);

            if (! in_array($locked->status, [ReturnStatus::Draft, ReturnStatus::Approved], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft or approved customer returns can be cancelled.'],
                ]);
            }

            $locked->status = ReturnStatus::Cancelled;
            $locked->save();

            return $locked;
        });
    }

    private function assertCustomerParty(ReturnOrder $return_order): void
    {
        $is_customer = Party::query()
            ->whereKey((int) $return_order->party_id)
            ->where('company_id', (int) $return_order->company_id)
            ->where('is_customer', true)
            ->exists();

        if (! $is_customer) {
            throw ValidationException::withMessages([
                'party_id' => ['Customer return party must be a customer for the same company.'],
            ]);
        }
    }

    /**
     * @return array<int, array{quantity: string, unit_price: string}>
     */
    private function creditNoteLineOverrides(ReturnOrder $return_order, Invoice $invoice): array
    {
        $invoice_lines = InvoiceLine::query()
            ->where('invoice_id', (int) $invoice->id)
            ->orderBy('line_no')
            ->get()
            ->keyBy(static fn (InvoiceLine $line): int => (int) $line->id);

        if ($invoice_lines->isEmpty()) {
            throw ValidationException::withMessages([
                'invoice_id' => ['The source invoice has no lines to credit.'],
            ]);
        }

        $line_overrides = [];

        foreach ($invoice_lines as $invoice_line) {
            $line_overrides[(int) $invoice_line->id] = [
                'quantity' => '0.0000',
                'unit_price' => (string) $invoice_line->unit_price,
            ];
        }

        foreach ($return_order->lines as $line) {
            /** @var ReturnOrderLine $line */
            if ($line->invoice_line_id === null) {
                throw ValidationException::withMessages([
                    'invoice_line_id' => ['Every returned line must reference a source invoice line before creating a credit note.'],
                ]);
            }

            $invoice_line_id = (int) $line->invoice_line_id;

            if (! $invoice_lines->has($invoice_line_id)) {
                throw ValidationException::withMessages([
                    'invoice_line_id' => ['Returned line references an invoice line outside the source invoice.'],
                ]);
            }

            $line_overrides[$invoice_line_id]['quantity'] = $this->formatQuantity(
                (float) $line_overrides[$invoice_line_id]['quantity'] + (float) $line->quantity,
            );
        }

        $has_returned_quantity = collect($line_overrides)
            ->contains(static fn (array $override): bool => (float) $override['quantity'] > 0.0);

        if (! $has_returned_quantity) {
            throw ValidationException::withMessages([
                'lines' => ['Credit note must have at least one returned invoice line quantity.'],
            ]);
        }

        return $line_overrides;
    }

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }
}
