<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Quotations;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Models\Opportunity;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\QuotationItem;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ConnectionScopedTransaction;

final readonly class QuotationRevisionService
{
    public function createRevision(Quotation $quotation): Quotation
    {
        return ConnectionScopedTransaction::run($quotation, function (ConnectionScopedModels $models) use ($quotation): Quotation {
            $quotation_query = $models->query(Quotation::class);
            $models->model(QuotationItem::class);
            $models->model(Party::class);
            $models->model(Opportunity::class);
            $source = $quotation_query
                ->with('quotation_items')
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            if ($source->status === QuoteStatus::Draft && ! $source->isLocked()) {
                throw ValidationException::withMessages([
                    'status' => ['An editable draft must be changed directly instead of creating a revision.'],
                ]);
            }

            if ($models->query(Quotation::class)->where('revises_quotation_id', $source->id)->exists()) {
                throw ValidationException::withMessages([
                    'revises_quotation_id' => ['This quotation already has a subsequent revision. Revise the latest quotation instead.'],
                ]);
            }

            if ($source->version >= 255) {
                throw ValidationException::withMessages([
                    'version' => ['The quotation revision limit has been reached.'],
                ]);
            }

            $revision = $models->query(Quotation::class)->create([
                'company_id' => $source->company_id,
                'party_id' => $source->party_id,
                'opportunity_id' => $source->opportunity_id,
                'currency' => $source->currency,
                'notes' => $source->notes,
                'status' => QuoteStatus::Draft,
                'version' => $source->version + 1,
                'revises_quotation_id' => $source->id,
                'valid_from' => $source->valid_from,
                'valid_to' => $source->valid_to,
            ]);

            foreach ($source->quotation_items as $item) {
                $revision->quotation_items()->create([
                    'name' => $item->name,
                    'billing_mode' => $item->billing_mode,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'price_list_item_id' => $item->price_list_item_id,
                ]);
            }

            return $revision->load('quotation_items');
        });
    }
}
