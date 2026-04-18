<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Helpers\HasValidity;

// use Modules\Business\Database\Factories\QuotationFactory;

/**
 * @mixin IdeHelperQuotation
 */
class Quotation extends Model
{
    use HasFactory;
    use HasValidity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): QuotationFactory
    // {
    //     // return QuoteFactory::new();
    // }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation_items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }
}
