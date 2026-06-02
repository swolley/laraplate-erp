<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\ReturnOrder;

final readonly class ReturnOrderService
{
    public function __construct(
        private CustomerReturnReceiptService $receipt_service,
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
}
