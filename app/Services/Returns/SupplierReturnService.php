<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\SupplierReturnLine;

final readonly class SupplierReturnService
{
    public function __construct(
        private SupplierReturnShipmentService $shipment_service,
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
        return $this->shipment_service->ship($supplier_return);
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

            $debit_note = Invoice::query()->create([
                'company_id' => $locked->company_id,
                'party_id' => $locked->party_id,
                'direction' => InvoiceDirection::Purchase->value,
                'invoice_type' => InvoiceType::DebitNote->value,
                'currency' => $purchase_order->currency,
                'notes' => 'Debit note for supplier return ' . ($locked->reference ?? '#' . $locked->id),
            ]);

            $line_no = 1;

            foreach ($locked->lines as $line) {
                /** @var SupplierReturnLine $line */
                $purchase_order_line = $this->purchaseOrderLineForDebitNote($line, $purchase_order);

                $debit_note->lines()->create([
                    'line_no' => $line_no++,
                    'description' => $purchase_order_line->name,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => (string) $purchase_order_line->unit_price,
                    'purchase_order_line_id' => $purchase_order_line->id,
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

    private function purchaseOrderLineForDebitNote(SupplierReturnLine $line, PurchaseOrder $purchase_order): PurchaseOrderLine
    {
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

        if ($purchase_order_line->unit_price === null) {
            throw ValidationException::withMessages([
                'unit_price' => ['Source purchase order line requires a unit price before creating a debit note.'],
            ]);
        }

        return $purchase_order_line;
    }
}
