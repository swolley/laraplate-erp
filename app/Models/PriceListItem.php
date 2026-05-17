<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperPriceListItem
 */
final class PriceListItem extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::PriceListItems->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'price_list_id',
        'taxonomy_id',
        'name',
        'uom',
        'unit_price',
    ];

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'taxonomy_id');
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function price_list(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'price_list_id' => ['required', 'integer', 'exists:' . ERPTables::PriceLists->value . ',id'],
            'taxonomy_id' => ['required', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
            'name' => ['required', 'string', 'max:255'],
            'uom' => ['nullable', 'string', 'max:64'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'price_list_id' => ['sometimes', 'integer', 'exists:' . ERPTables::PriceLists->value . ',id'],
            'taxonomy_id' => ['sometimes', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'uom' => ['nullable', 'string', 'max:64'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
        ];
    }
}
