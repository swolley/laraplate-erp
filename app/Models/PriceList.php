<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;

/**
 * @mixin IdeHelperPriceList
 */
class PriceList extends Model
{
    protected $table = 'price_lists';

    /**
     * @return HasMany<PriceListItem>
     */
    public function price_list_items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }
}
