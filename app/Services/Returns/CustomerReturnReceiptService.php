<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\ReturnOrderLine;
use Modules\ERP\Services\Inventory\StockMovementService;

final readonly class CustomerReturnReceiptService
{
    public function __construct(
        private StockMovementService $stockMovementService,
    ) {}

    public function receive(ReturnOrder $return_order): ReturnOrder
    {
        if ($return_order->status !== ReturnStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => ['The return order must be approved before it can be processed.'],
            ]);
        }

        return DB::transaction(function () use ($return_order): ReturnOrder {
            $return_order = ReturnOrder::query()->with('lines')->lockForUpdate()->findOrFail($return_order->getKey());

            if ($return_order->status !== ReturnStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => ['The return order must be approved before it can be processed.'],
                ]);
            }

            foreach ($return_order->lines as $line) {
                /** @var ReturnOrderLine $line */
                $this->stockMovementService->recordInbound(
                    (int) $return_order->company_id,
                    (int) $line->item_id,
                    (int) $line->warehouse_id,
                    (int) $line->quantity,
                    (string) $line->unit_cost,
                    $line,
                );
            }

            $return_order->status = ReturnStatus::Processed;
            $return_order->processed_at = now();
            $return_order->save();

            return $return_order;
        });
    }
}
