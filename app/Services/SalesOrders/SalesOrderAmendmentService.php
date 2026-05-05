<?php

declare(strict_types=1);

namespace Modules\ERP\Services\SalesOrders;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;

/**
 * Creates a new draft amendment order from an existing sales order.
 */
final class SalesOrderAmendmentService
{
    public function __construct(
        private readonly DocumentNumberAllocator $document_number_allocator,
    ) {}

    public function amend(SalesOrder $source_order): SalesOrder
    {
        return DB::transaction(function () use ($source_order): SalesOrder {
            /** @var SalesOrder $locked_source */
            $locked_source = SalesOrder::query()
                ->whereKey((int) $source_order->getKey())
                ->with('lines')
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked_source->status, [
                SalesOrderStatus::CONFIRMED,
                SalesOrderStatus::PARTIALLY_EVASED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only confirmed or partially evased sales orders can be amended.'],
                ]);
            }

            if ($locked_source->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Sales order must contain at least one line to create an amendment.'],
                ]);
            }

            $company = Company::query()->withoutGlobalScopes()->findOrFail((int) $locked_source->company_id);
            $new_reference = $this->document_number_allocator->next($company, DocumentType::SalesOrder, 0);

            /** @var SalesOrder $amendment */
            $amendment = SalesOrder::query()->create([
                'company_id' => (int) $locked_source->company_id,
                'customer_id' => (int) $locked_source->customer_id,
                'quotation_id' => $locked_source->quotation_id,
                'project_id' => $locked_source->project_id,
                'amends_sales_order_id' => (int) $locked_source->id,
                'reference' => $new_reference,
                'currency' => (string) $locked_source->currency,
                'status' => SalesOrderStatus::DRAFT,
                'notes' => $locked_source->notes,
            ]);

            foreach ($locked_source->lines as $source_line) {
                $delivered_or_invoiced = max((int) $source_line->qty_delivered, (int) $source_line->qty_invoiced);
                $remaining_qty = max((int) $source_line->qty_ordered - $delivered_or_invoiced, 0);

                if ($remaining_qty === 0) {
                    continue;
                }

                $amendment->lines()->create([
                    'quotation_item_id' => $source_line->quotation_item_id,
                    'item_id' => $source_line->item_id,
                    'name' => $source_line->name,
                    'qty_ordered' => $remaining_qty,
                    'qty_delivered' => 0,
                    'qty_invoiced' => 0,
                    'unit_price' => $source_line->unit_price,
                    'status' => SalesOrderLineStatus::OPEN,
                ]);
            }

            if (! $amendment->lines()->exists()) {
                throw ValidationException::withMessages([
                    'lines' => ['No remaining quantities are available to amend.'],
                ]);
            }

            return $amendment->fresh('lines');
        });
    }
}
