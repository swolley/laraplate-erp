<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @mixin IdeHelperPriceList
 */
class PriceList extends Model
{
    use HasValidity;

    protected $table = 'price_lists';

    /**
     * The attributes that are mass assignable.
     */
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
