<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Data\Returns\ReturnLineCreditOverride;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\SupplierReturnLine;
use Modules\ERP\Services\Company\ErpCompanySettings;
use Modules\ERP\Services\Inventory\DeliveryNoteInventoryService;

final readonly class SupplierReturnService
{
    public function __construct(
        private SupplierReturnShipmentService $shipment_service,
        private DeliveryNoteInventoryService $delivery_note_inventory_service,
        private ErpCompanySettings $erp_company_settings,
    ) {}

    public function approve(SupplierReturn $supplier_return): SupplierReturn
    {
        return DB::transaction(function () use ($supplier_return): SupplierReturn {
            /** @var SupplierReturn $locked */
            $locked = SupplierReturn::query()->lockForUpdate()->findOrFail((int) $supplier_return->id);

            if ($locked->status !== ReturnStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => ['Supplier return can only be approved from draft.'],
                ]);
            }

            $this->assertSupplierParty($locked);

            $locked->status = ReturnStatus::Approved;
            $locked->save();

            return $locked;
        });
    }

    public function complete(SupplierReturn $supplier_return): SupplierReturn
    {
        return DB::transaction(function () use ($supplier_return): SupplierReturn {
            $processed = $this->shipment_service->ship($supplier_return);

            if ($this->shouldAutoCreateDebitNote($processed)) {
                $this->createDebitNote($processed);
                $processed = $processed->fresh() ?? $processed;
            }

            return $processed;
        });
    }

    public function reverseProcessed(SupplierReturn $supplier_return): SupplierReturn
    {
        return DB::transaction(function () use ($supplier_return): SupplierReturn {
            /** @var SupplierReturn $locked */
            $locked = SupplierReturn::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail((int) $supplier_return->id);

            if ($locked->status !== ReturnStatus::Processed) {
                throw ValidationException::withMessages([
                    'status' => ['Only processed supplier returns can be reversed.'],
                ]);
            }

            if ($locked->debit_note_invoice_id !== null) {
                throw ValidationException::withMessages([
                    'debit_note_invoice_id' => ['Reverse or remove the linked debit note before reversing this supplier return.'],
                ]);
            }

            if ($locked->delivery_note_id === null) {
                throw ValidationException::withMessages([
                    'delivery_note_id' => ['Processed supplier return is missing its generated delivery note.'],
                ]);
            }

            /** @var DeliveryNote $delivery_note */
            $delivery_note = DeliveryNote::query()
                ->whereKey($locked->delivery_note_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $delivery_note->company_id !== (int) $locked->company_id) {
                throw ValidationException::withMessages([
                    'delivery_note_id' => ['Supplier return delivery note belongs to a different company.'],
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

    public function createDebitNote(SupplierReturn $supplier_return): Invoice
    {
        return DB::transaction(function () use ($supplier_return): Invoice {
            /** @var SupplierReturn $locked */
            $locked = SupplierReturn::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail((int) $supplier_return->id);

            if ($locked->status !== ReturnStatus::Processed) {
                throw ValidationException::withMessages([
                    'status' => ['Supplier return must be processed before creating a debit note.'],
                ]);
            }

            if ($locked->debit_note_invoice_id !== null) {
                throw ValidationException::withMessages([
                    'debit_note_invoice_id' => ['This supplier return already has a linked debit note.'],
                ]);
            }

            if ($locked->purchase_order_id === null) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => ['Supplier return requires a source purchase order before creating a debit note.'],
                ]);
            }

            /** @var PurchaseOrder $purchase_order */
            $purchase_order = PurchaseOrder::query()
                ->whereKey((int) $locked->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $purchase_order->company_id !== (int) $locked->company_id
                || (int) $purchase_order->party_id !== (int) $locked->party_id) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => ['The source purchase order must belong to the same company and supplier as the return.'],
                ]);
            }

            $line_overrides = $this->buildDebitOverrides($locked);
            $source_invoice = $this->sourceInvoiceFromDebitOverrides($line_overrides);

            $debit_note = Invoice::query()->create([
                'company_id' => $locked->company_id,
                'party_id' => $locked->party_id,
                'direction' => InvoiceDirection::Purchase->value,
                'invoice_type' => InvoiceType::DebitNote->value,
                'credited_invoice_id' => $source_invoice->id,
                'currency' => $source_invoice->currency,
                'notes' => 'Debit note for supplier return ' . ($locked->reference ?? '#' . $locked->id),
            ]);

            $line_no = 1;

            foreach ($locked->lines as $line) {
                /** @var SupplierReturnLine $line */
                $invoice_line = $this->invoiceLineForDebitNote($line, $locked, $purchase_order);

                $debit_note->lines()->create([
                    'line_no' => $line_no++,
                    'description' => $invoice_line->description,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => (string) ($line->unit_price ?? $invoice_line->unit_price),
                    'purchase_order_line_id' => $line->purchase_order_line_id ?? $invoice_line->purchase_order_line_id,
                    'goods_receipt_line_id' => $line->goods_receipt_line_id,
                ]);
            }

            if ($line_no === 1) {
                throw ValidationException::withMessages([
                    'lines' => ['Debit note must have at least one supplier return line.'],
                ]);
            }

            $locked->debit_note_invoice_id = is_int($debit_note->id) ? $debit_note->id : (int) $debit_note->id;
            $locked->save();

            return $debit_note;
        });
    }

    /**
     * @return array<int, ReturnLineCreditOverride>
     */
    public function buildDebitOverrides(SupplierReturn $supplier_return): array
    {
        if ($supplier_return->purchase_order_id === null) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Supplier return requires a source purchase order before creating debit overrides.'],
            ]);
        }

        $supplier_return->loadMissing('lines');

        /** @var PurchaseOrder $purchase_order */
        $purchase_order = PurchaseOrder::query()->whereKey((int) $supplier_return->purchase_order_id)->firstOrFail();

        if ((int) $purchase_order->company_id !== (int) $supplier_return->company_id
            || (int) $purchase_order->party_id !== (int) $supplier_return->party_id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['The source purchase order must belong to the same company and supplier as the return.'],
            ]);
        }

        /** @var array<int, ReturnLineCreditOverride> $line_overrides */
        $line_overrides = [];
        $source_invoice_id = null;

        foreach ($supplier_return->lines as $line) {
            $invoice_line = $this->invoiceLineForDebitNote($line, $supplier_return, $purchase_order);
            /** @var Invoice $invoice */
            $invoice = $invoice_line->invoice;

            if ($source_invoice_id !== null && $source_invoice_id !== (int) $invoice->id) {
                throw ValidationException::withMessages([
                    'invoice_line_id' => ['Supplier return debit note lines must reference one source purchase invoice.'],
                ]);
            }

            $source_invoice_id = (int) $invoice->id;
            $invoice_line_id = (int) $invoice_line->id;

            $current_quantity = $line_overrides[$invoice_line_id]->quantity ?? '0.0000';

            $line_overrides[$invoice_line_id] = new ReturnLineCreditOverride(
                source_line_id: $invoice_line_id,
                quantity: $this->formatQuantity((float) $current_quantity + (float) $line->quantity),
                unit_price: (string) ($line->unit_price ?? $invoice_line->unit_price),
            );
        }

        if ($line_overrides === []) {
            throw ValidationException::withMessages([
                'lines' => ['Debit note must have at least one supplier return line.'],
            ]);
        }

        return $line_overrides;
    }

    public function cancel(SupplierReturn $supplier_return): SupplierReturn
    {
        return DB::transaction(function () use ($supplier_return): SupplierReturn {
            /** @var SupplierReturn $locked */
            $locked = SupplierReturn::query()->lockForUpdate()->findOrFail((int) $supplier_return->id);

            if (! in_array($locked->status, [ReturnStatus::Draft, ReturnStatus::Approved], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft or approved supplier returns can be cancelled.'],
                ]);
            }

            $locked->status = ReturnStatus::Cancelled;
            $locked->save();

            return $locked;
        });
    }

    private function unregisterSourceReturnedQuantities(SupplierReturn $supplier_return): void
    {
        foreach ($supplier_return->lines as $line) {
            $quantity = (float) $line->quantity;
            $purchase_order_line_id = $line->purchase_order_line_id;

            if ($line->goods_receipt_line_id !== null) {
                /** @var GoodsReceiptLine $goods_receipt_line */
                $goods_receipt_line = GoodsReceiptLine::query()
                    ->whereKey($line->goods_receipt_line_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $new_goods_returned = (float) $goods_receipt_line->qty_returned - $quantity;

                if ($new_goods_returned < -0.00005) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Cannot reverse supplier return because source goods receipt returned quantity would become negative.'],
                    ]);
                }

                $goods_receipt_line->qty_returned = $this->formatQuantity(max(0.0, $new_goods_returned));
                $goods_receipt_line->save();

                if ($purchase_order_line_id === null && $goods_receipt_line->purchase_order_line_id !== null) {
                    $purchase_order_line_id = $goods_receipt_line->purchase_order_line_id;
                }
            }

            if ($purchase_order_line_id === null) {
                continue;
            }

            /** @var PurchaseOrderLine $purchase_order_line */
            $purchase_order_line = PurchaseOrderLine::query()
                ->whereKey($purchase_order_line_id)
                ->lockForUpdate()
                ->firstOrFail();

            $new_purchase_returned = (float) $purchase_order_line->qty_returned - $quantity;

            if ($new_purchase_returned < -0.00005) {
                throw ValidationException::withMessages([
                    'quantity' => ['Cannot reverse supplier return because source purchase order returned quantity would become negative.'],
                ]);
            }

            $purchase_order_line->qty_returned = $this->formatQuantity(max(0.0, $new_purchase_returned));
            $purchase_order_line->save();
        }
    }

    private function assertSupplierParty(SupplierReturn $supplier_return): void
    {
        $is_supplier = Party::query()
            ->whereKey((int) $supplier_return->party_id)
            ->where('company_id', (int) $supplier_return->company_id)
            ->where('is_supplier', true)
            ->exists();

        if (! $is_supplier) {
            throw ValidationException::withMessages([
                'party_id' => ['Supplier return party must be a supplier for the same company.'],
            ]);
        }
    }

    private function shouldAutoCreateDebitNote(SupplierReturn $supplier_return): bool
    {
        if ($supplier_return->debit_note_invoice_id !== null || $supplier_return->purchase_order_id === null) {
            return false;
        }

        $company = $supplier_return->company()->firstOrFail();

        return $this->erp_company_settings->autoCreateNotesOnComplete($company);
    }

    private function invoiceLineForDebitNote(
        SupplierReturnLine $line,
        SupplierReturn $supplier_return,
        PurchaseOrder $purchase_order,
    ): InvoiceLine {
        if ($line->invoice_line_id === null) {
            throw ValidationException::withMessages([
                'invoice_line_id' => ['Every supplier return line must reference a source purchase invoice line before creating a debit note.'],
            ]);
        }

        /** @var InvoiceLine $invoice_line */
        $invoice_line = InvoiceLine::query()
            ->with('invoice')
            ->whereKey((int) $line->invoice_line_id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var Invoice|null $invoice */
        $invoice = $invoice_line->invoice;

        if (! $invoice instanceof Invoice
            || (int) $invoice->company_id !== (int) $supplier_return->company_id
            || (int) $invoice->party_id !== (int) $supplier_return->party_id
            || $invoice->direction !== InvoiceDirection::Purchase
            || $invoice->invoice_type !== InvoiceType::Invoice) {
            throw ValidationException::withMessages([
                'invoice_line_id' => ['Supplier return line must reference a purchase invoice line for the same company and supplier.'],
            ]);
        }

        if ($line->purchase_order_line_id === null) {
            throw ValidationException::withMessages([
                'purchase_order_line_id' => ['Every supplier return line must reference a source purchase order line before creating a debit note.'],
            ]);
        }

        /** @var PurchaseOrderLine $purchase_order_line */
        $purchase_order_line = PurchaseOrderLine::query()
            ->whereKey((int) $line->purchase_order_line_id)
            ->where('purchase_order_id', (int) $purchase_order->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($invoice_line->purchase_order_line_id === null
            || (int) $invoice_line->purchase_order_line_id !== (int) $purchase_order_line->id) {
            throw ValidationException::withMessages([
                'invoice_line_id' => ['Supplier return invoice line must match the source purchase order line.'],
            ]);
        }

        return $invoice_line;
    }

    /**
     * @param  array<int, ReturnLineCreditOverride>  $line_overrides
     */
    private function sourceInvoiceFromDebitOverrides(array $line_overrides): Invoice
    {
        $source_line_id = array_key_first($line_overrides);

        if ($source_line_id === null) {
            throw ValidationException::withMessages([
                'lines' => ['Debit note must have at least one supplier return line.'],
            ]);
        }

        /** @var InvoiceLine $invoice_line */
        $invoice_line = InvoiceLine::query()->with('invoice')->findOrFail($source_line_id);

        /** @var Invoice $invoice */
        $invoice = $invoice_line->invoice;

        return $invoice;
    }

    /**
     * @return numeric-string
     */
    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }
}
