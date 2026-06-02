<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\SupplierReturn;

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
}
