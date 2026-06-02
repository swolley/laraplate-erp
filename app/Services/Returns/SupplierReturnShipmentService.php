<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\SupplierReturnLine;
use Modules\ERP\Services\Inventory\StockMovementService;

final readonly class SupplierReturnShipmentService
{
    public function __construct(
        private StockMovementService $stockMovementService,
    ) {}

    public function ship(SupplierReturn $supplier_return): SupplierReturn
    {
        if ($supplier_return->status !== ReturnStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => ['The supplier return must be approved before it can be processed.'],
            ]);
        }

        return DB::transaction(function () use ($supplier_return): SupplierReturn {
            $supplier_return = SupplierReturn::query()->with('lines')->lockForUpdate()->findOrFail($supplier_return->getKey());

            if ($supplier_return->status !== ReturnStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => ['The supplier return must be approved before it can be processed.'],
                ]);
            }

            foreach ($supplier_return->lines as $line) {
                /** @var SupplierReturnLine $line */
                $this->stockMovementService->recordOutbound(
                    (int) $supplier_return->company_id,
                    (int) $line->item_id,
                    (int) $line->warehouse_id,
                    (int) $line->quantity,
                    $line,
                );
            }

            $supplier_return->status = ReturnStatus::Processed;
            $supplier_return->processed_at = now();
            $supplier_return->save();

            return $supplier_return;
        });
    }
}
