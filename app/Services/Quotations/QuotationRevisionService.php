<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Quotations;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Models\Quotation;

final readonly class QuotationRevisionService
{
    public function createRevision(Quotation $quotation): Quotation
    {
        return DB::transaction(function () use ($quotation): Quotation {
            $source = Quotation::query()
                ->with('quotation_items')
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            if ($source->status === QuoteStatus::Draft && ! $source->isLocked()) {
                throw ValidationException::withMessages([
                    'status' => ['An editable draft must be changed directly instead of creating a revision.'],
                ]);
            }

            if (Quotation::query()->where('revises_quotation_id', $source->id)->exists()) {
                throw ValidationException::withMessages([
                    'revises_quotation_id' => ['This quotation already has a subsequent revision. Revise the latest quotation instead.'],
                ]);
            }

            if ($source->version >= 255) {
                throw ValidationException::withMessages([
                    'version' => ['The quotation revision limit has been reached.'],
                ]);
            }

            $revision = Quotation::query()->create([
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
