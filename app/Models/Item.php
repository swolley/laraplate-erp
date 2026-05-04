<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;

/**
 * @mixin IdeHelperItem
 */
class Item extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'sku',
        'uom',
        'costing_method',
        'taxonomy_id',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stock_levels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stock_movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64'],
            'uom' => ['required', 'string', 'max:16'],
            'costing_method' => ['required', 'string', 'in:fifo,weighted_avg'],
            'taxonomy_id' => ['nullable', 'integer', 'exists:taxonomies,id'],
        ]);

        return $rules;
    }
}
