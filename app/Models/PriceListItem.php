<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasValidity;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property numeric-string $unit_price
 * @mixin \Eloquent
 * @mixin IdeHelperPriceListItem
 */
final class PriceListItem extends Model
{
    use HasValidity;

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
        'item_id',
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
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function price_list(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'price_list_id' => ['required', 'integer', 'exists:' . ERPTables::PriceLists->value . ',id'],
            'item_id' => ['nullable', 'integer', 'exists:' . ERPTables::Items->value . ',id'],
            'taxonomy_id' => ['nullable', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
            'name' => ['required', 'string', 'max:255'],
            'uom' => ['nullable', 'string', 'max:64'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'price_list_id' => ['sometimes', 'integer', 'exists:' . ERPTables::PriceLists->value . ',id'],
            'item_id' => ['nullable', 'integer', 'exists:' . ERPTables::Items->value . ',id'],
            'taxonomy_id' => ['nullable', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'uom' => ['nullable', 'string', 'max:64'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (PriceListItem $price_list_item): void {
            if (($price_list_item->item_id === null) === ($price_list_item->taxonomy_id === null)) {
                throw ValidationException::withMessages([
                    'item_id' => ['Exactly one of item_id or taxonomy_id is required.'],
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
        ];
    }
}
