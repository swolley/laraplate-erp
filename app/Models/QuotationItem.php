<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;

/**
 * @mixin IdeHelperQuotationItem
 */
class QuotationItem extends Model
{
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function price_list_item(): BelongsTo
    {
        return $this->belongsTo(PriceListItem::class);
    }
}
