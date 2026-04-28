<?php

declare(strict_types=1);

namespace Modules\Business\Services\Taxation;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Business\Models\TaxCode;

/**
 * Deactivates a superseded row and points it to the replacement {@see TaxCode}.
 *
 * Uses a direct `tax_codes` update so Core model versioning does not record a half-broken diff on system-only columns.
 */
final class TaxCodeSupersessionService
{
    public function linkReplacement(TaxCode $obsolete, TaxCode $replacement): void
    {
        if ((int) $obsolete->company_id !== (int) $replacement->company_id) {
            throw new InvalidArgumentException('Tax codes must belong to the same company.');
        }

        if ((int) $obsolete->getKey() === (int) $replacement->getKey()) {
            throw new InvalidArgumentException('Cannot link a tax code as replacement of itself.');
        }

        DB::table('tax_codes')
            ->where('id', $obsolete->getKey())
            ->update([
                'is_active' => false,
                'replaced_by_tax_code_id' => $replacement->getKey(),
                'updated_at' => now(),
            ]);
    }
}
