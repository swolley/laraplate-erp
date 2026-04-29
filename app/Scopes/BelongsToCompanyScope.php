<?php

declare(strict_types=1);

namespace Modules\ERP\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Override;

use function Modules\ERP\Helpers\current_company_id;

/**
 * Restricts queries on tenant-aware Business models to the active company.
 *
 * Resolution rules (see {@see current_company_id()}):
 * - explicit container binding `erp.current_company_id` if set;
 * - otherwise the authenticated user's `company_id`;
 * - otherwise no filter is applied (bootstrap mode for CLI/seeders).
 *
 * The scope qualifies the column with the model table to play nicely with
 * joins and avoid ambiguous-column errors.
 */
final class BelongsToCompanyScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    #[Override]
    public function apply(Builder $builder, Model $model): void
    {
        $company_id = current_company_id();

        if ($company_id === null) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $company_id);
    }
}
