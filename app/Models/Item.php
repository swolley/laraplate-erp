<?php

declare(strict_types=1);

namespace Modules\ERP\Models;
use Modules\Core\Enums\CoreTables;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperItem
 */
final class Item extends Model
{
    use BelongsToCompany;

    #[Override]
    protected $table = ERPTables::Items->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
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
            'company_id' => ['required', 'integer', 'exists:'.ERPTables::Companies->value.',id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64'],
            'uom' => ['required', 'string', 'max:16'],
            'costing_method' => ['required', 'string', 'in:fifo,weighted_avg'],
            'taxonomy_id' => ['nullable', 'integer', 'exists:'.CoreTables::Taxonomies->value.',id'],
        ]);

        return $rules;
    }
}
