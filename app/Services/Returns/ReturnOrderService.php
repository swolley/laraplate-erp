<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Data\Returns\ReturnLineCreditOverride;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\ReturnOrderLine;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Services\Accounting\CreditNoteService;
use Modules\ERP\Services\Company\ErpCompanySettings;
use Modules\ERP\Services\Inventory\DeliveryNoteInventoryService;

final readonly class ReturnOrderService
{
    public function __construct(
        private CustomerReturnReceiptService $receipt_service,
        private DeliveryNoteInventoryService $delivery_note_inventory_service,
        private CreditNoteService $credit_note_service,
        private ErpCompanySettings $erp_company_settings,
    ) {}

    public function approve(ReturnOrder $return_order): ReturnOrder
    {
        return DB::transaction(function () use ($return_order): ReturnOrder {
            $locked = ReturnOrder::query()->lockForUpdate()->whereKey($return_order->id)->firstOrFail();

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
        return DB::transaction(function () use ($return_order): ReturnOrder {
            $processed = $this->receipt_service->receive($return_order);

            if ($this->shouldAutoCreateCreditNote($processed)) {
                $this->createCreditNote($processed);
                $processed = $processed->fresh() ?? $processed;
            }

            return $processed;
        });
    }

    public function reverseProcessed(ReturnOrder $return_order): ReturnOrder
    {
        return DB::transaction(function () use ($return_order): ReturnOrder {
            /** @var ReturnOrder $locked */
            $locked = ReturnOrder::query()
                ->with('lines')
                ->lockForUpdate()
                ->whereKey($return_order->id)
                ->firstOrFail();

            if ($locked->status !== ReturnStatus::Processed) {
                throw ValidationException::withMessages([
                    'status' => ['Only processed customer returns can be reversed.'],
                ]);
            }

            if ($locked->credit_note_invoice_id !== null) {
                throw ValidationException::withMessages([
                    'credit_note_invoice_id' => ['Reverse or remove the linked credit note before reversing this customer return.'],
                ]);
            }

            if ($locked->delivery_note_id === null) {
                throw ValidationException::withMessages([
                    'delivery_note_id' => ['Processed customer return is missing its generated delivery note.'],
                ]);
            }

            /** @var DeliveryNote $delivery_note */
            $delivery_note = DeliveryNote::query()
                ->whereKey($locked->delivery_note_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $delivery_note->company_id !== (int) $locked->company_id) {
                throw ValidationException::withMessages([
                    'delivery_note_id' => ['Customer return delivery note belongs to a different company.'],
                ]);
            }

            if ($delivery_note->posted_at !== null) {
                $delivery_note->posted_at = null;
                $delivery_note->save();
            } elseif ($delivery_note->inventory_posted_at !== null) {
                $this->delivery_note_inventory_service->unpostInventory($delivery_note);
                $delivery_note->save();
            }

            $this->unregisterSourceReturnedQuantities($locked);

            $locked->status = ReturnStatus::Approved;
            $locked->processed_at = null;
            $locked->save();

            return $locked;
        });
    }

    public function createCreditNote(ReturnOrder $return_order): Invoice
    {
        return DB::transaction(function () use ($return_order): Invoice {
            $locked = ReturnOrder::query()
                ->with('lines')
                ->lockForUpdate()
                ->whereKey($return_order->id)
                ->firstOrFail();

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

            $invoice = Invoice::query()
                ->whereKey($locked->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->company_id !== $locked->company_id) {
                throw ValidationException::withMessages([
                    'invoice_id' => ['The source invoice must belong to the same company as the customer return.'],
                ]);
            }

            $line_overrides = $this->creditNoteLineOverridePayload(
                $this->buildCreditOverrides($locked),
            );
            $credit_note = $this->credit_note_service->createFromInvoice($invoice, $line_overrides);

            $locked->credit_note_invoice_id = $this->modelId($credit_note);
            $locked->save();

            return $credit_note;
        });
    }

    public function cancel(ReturnOrder $return_order): ReturnOrder
    {
        return DB::transaction(function () use ($return_order): ReturnOrder {
            $locked = ReturnOrder::query()->lockForUpdate()->whereKey($return_order->id)->firstOrFail();

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

    private function unregisterSourceReturnedQuantities(ReturnOrder $return_order): void
    {
        foreach ($return_order->lines as $line) {
            if ($line->invoice_line_id === null) {
                continue;
            }

            /** @var InvoiceLine $invoice_line */
            $invoice_line = InvoiceLine::query()
                ->whereKey($line->invoice_line_id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantity = (float) $line->quantity;
            $new_invoice_returned = (float) $invoice_line->qty_returned - $quantity;

            if ($new_invoice_returned < -0.00005) {
                throw ValidationException::withMessages([
                    'quantity' => ['Cannot reverse customer return because source invoice returned quantity would become negative.'],
                ]);
            }

            $invoice_line->qty_returned = $this->formatQuantity(max(0.0, $new_invoice_returned));
            $invoice_line->save();

            if ($invoice_line->sales_order_line_id === null) {
                continue;
            }

            /** @var SalesOrderLine $sales_order_line */
            $sales_order_line = SalesOrderLine::query()
                ->whereKey($invoice_line->sales_order_line_id)
                ->lockForUpdate()
                ->firstOrFail();

            $new_sales_returned = (float) $sales_order_line->qty_returned - $quantity;

            if ($new_sales_returned < -0.00005) {
                throw ValidationException::withMessages([
                    'quantity' => ['Cannot reverse customer return because source sales order returned quantity would become negative.'],
                ]);
            }

            $sales_order_line->qty_returned = $this->formatQuantity(max(0.0, $new_sales_returned));
            $sales_order_line->save();
        }
    }

    private function assertCustomerParty(ReturnOrder $return_order): void
    {
        $is_customer = Party::query()
            ->whereKey($return_order->party_id)
            ->where('company_id', $return_order->company_id)
            ->where('is_customer', true)
            ->exists();

        if (! $is_customer) {
            throw ValidationException::withMessages([
                'party_id' => ['Customer return party must be a customer for the same company.'],
            ]);
        }
    }

    private function shouldAutoCreateCreditNote(ReturnOrder $return_order): bool
    {
        if ($return_order->credit_note_invoice_id !== null || $return_order->invoice_id === null) {
            return false;
        }

        $company = $return_order->company()->firstOrFail();

        return $this->erp_company_settings->autoCreateNotesOnComplete($company);
    }

    /**
     * @return array<int, ReturnLineCreditOverride>
     */
    public function buildCreditOverrides(ReturnOrder $return_order): array
    {
        if ($return_order->invoice_id === null) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Customer return requires a source invoice before creating credit overrides.'],
            ]);
        }

        $return_order->loadMissing('lines');

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->whereKey($return_order->invoice_id)->firstOrFail();

        if ((int) $invoice->company_id !== (int) $return_order->company_id) {
            throw ValidationException::withMessages([
                'invoice_id' => ['The source invoice must belong to the same company as the customer return.'],
            ]);
        }

        $invoice_lines = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->orderBy('line_no')
            ->get()
            ->keyBy(fn (InvoiceLine $line): int => $this->modelId($line));

        if ($invoice_lines->isEmpty()) {
            throw ValidationException::withMessages([
                'invoice_id' => ['The source invoice has no lines to credit.'],
            ]);
        }

        /** @var array<int, ReturnLineCreditOverride> $line_overrides */
        $line_overrides = [];

        foreach ($invoice_lines as $invoice_line) {
            $source_line_id = $this->modelId($invoice_line);

            $line_overrides[$source_line_id] = new ReturnLineCreditOverride(
                source_line_id: $source_line_id,
                quantity: '0.0000',
                unit_price: $invoice_line->unit_price,
            );
        }

        foreach ($return_order->lines as $line) {
            if ($line->invoice_line_id === null) {
                throw ValidationException::withMessages([
                    'invoice_line_id' => ['Every returned line must reference a source invoice line before creating a credit note.'],
                ]);
            }

            $invoice_line_id = $line->invoice_line_id;

            if (! $invoice_lines->has($invoice_line_id)) {
                throw ValidationException::withMessages([
                    'invoice_line_id' => ['Returned line references an invoice line outside the source invoice.'],
                ]);
            }

            /** @var ReturnLineCreditOverride $current */
            $current = $line_overrides[$invoice_line_id];

            /** @var InvoiceLine $invoice_line */
            $invoice_line = $invoice_lines->get($invoice_line_id);

            $line_overrides[$invoice_line_id] = new ReturnLineCreditOverride(
                source_line_id: $invoice_line_id,
                quantity: $this->formatQuantity((float) $current->quantity + (float) $line->quantity),
                unit_price: $line->unit_price ?? $invoice_line->unit_price,
            );
        }

        $has_returned_quantity = collect($line_overrides)
            ->contains(static fn (ReturnLineCreditOverride $override): bool => (float) $override->quantity > 0.0);

        if (! $has_returned_quantity) {
            throw ValidationException::withMessages([
                'lines' => ['Credit note must have at least one returned invoice line quantity.'],
            ]);
        }

        return $line_overrides;
    }

    /**
     * @param  array<int, ReturnLineCreditOverride>  $line_overrides
     * @return array<int, array{quantity: numeric-string, unit_price: numeric-string}>
     */
    private function creditNoteLineOverridePayload(array $line_overrides): array
    {
        return collect($line_overrides)
            ->mapWithKeys(static fn (ReturnLineCreditOverride $override): array => [
                $override->source_line_id => [
                    'quantity' => $override->quantity,
                    'unit_price' => $override->unit_price,
                ],
            ])
            ->all();
    }

    /**
     * @return numeric-string
     */
    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }

    private function modelId(Invoice|InvoiceLine $model): int
    {
        return is_int($model->id) ? $model->id : (int) $model->id;
    }
}
