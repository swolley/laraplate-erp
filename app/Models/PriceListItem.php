<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;

/**
 * @mixin IdeHelperPriceListItem
 */
class PriceListItem extends Model
{
    protected $table = 'price_lists_items';

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function price_list(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
