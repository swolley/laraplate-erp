<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Taxation;

use InvalidArgumentException;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Support\ConnectionScopedTransaction;

/**
 * Deactivates a superseded row and points it to the replacement {@see TaxCode}.
 *
 * Uses a direct `tax_codes` update so Core model versioning does not record a half-broken diff on system-only columns.
 */
final class TaxCodeSupersessionService
{
    public function linkReplacement(TaxCode $obsolete, TaxCode $replacement): void
    {
        throw_if($obsolete->company_id !== $replacement->company_id, InvalidArgumentException::class, 'Tax codes must belong to the same company.');

        throw_if($obsolete->id === $replacement->id, InvalidArgumentException::class, 'Cannot link a tax code as replacement of itself.');

        ConnectionScopedTransaction::connection($obsolete, $replacement);

        $obsolete->getConnection()->table($obsolete->getTable())
            ->where('id', $obsolete->id)
            ->update([
                'is_active' => false,
                'replaced_by_tax_code_id' => $replacement->id,
                'updated_at' => now(),
            ]);
    }
}
