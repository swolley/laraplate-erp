<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Taxation;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\TaxCode;

/**
 * Deactivates a superseded row and points it to the replacement {@see TaxCode}.
 *
 * Uses a direct `tax_codes` update so Core model versioning does not record a half-broken diff on system-only columns.
 */
final class TaxCodeSupersessionService
{
    public function linkReplacement(TaxCode $obsolete, TaxCode $replacement): void
    {
        throw_if((int) $obsolete->company_id !== (int) $replacement->company_id, InvalidArgumentException::class, 'Tax codes must belong to the same company.');

        throw_if((int) $obsolete->getKey() === (int) $replacement->getKey(), InvalidArgumentException::class, 'Cannot link a tax code as replacement of itself.');

        $tax_codes_table = ERPTables::TaxCodes->value;
        DB::table($tax_codes_table)
            ->where('id', $obsolete->getKey())
            ->update([
                'is_active' => false,
                'replaced_by_tax_code_id' => $replacement->getKey(),
                'updated_at' => now(),
            ]);
    }
}
