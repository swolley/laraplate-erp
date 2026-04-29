<?php

declare(strict_types=1);

namespace Modules\ERP\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ERP\Models\Company;
use Modules\ERP\Scopes\BelongsToCompanyScope;

use function Modules\ERP\Helpers\current_company_id;

/**
 * Marks a model as company-scoped (multi-tenant).
 *
 * Behaviour added by booting this trait:
 * - global scope {@see BelongsToCompanyScope} that filters every query by the
 *   currently active company (no-op when no company is active, e.g. seeders);
 * - `creating` event hook that auto-fills `company_id` from the current
 *   company when the model is saved without an explicit value.
 *
 * Models using this trait MUST have a `company_id` foreign key column.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new BelongsToCompanyScope());

        static::creating(function ($model): void {
            if ($model->company_id !== null) {
                return;
            }

            $company_id = current_company_id();

            if ($company_id === null) {
                return;
            }

            $model->company_id = $company_id;
        });
    }

    public function initializeBelongsToCompany(): void
    {
        if (! in_array('company_id', $this->fillable, true)) {
            $this->fillable[] = 'company_id';
        }
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
