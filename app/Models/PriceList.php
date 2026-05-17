<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperPriceList
 */
final class PriceList extends Model
{
    use BelongsToCompany;
    use HasValidity;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::PriceLists->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'name',
        'currency',
    ];

    /**
     * @return HasMany<PriceListItem, $this>
     */
    public function price_list_items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        return $rules;
    }
}
