<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasValidity;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\DiscountType;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property DiscountType $discount_type
 * @property numeric-string $discount_value
 * @mixin \Eloquent
 * @mixin IdeHelperPartyPriceRule
 */
final class PartyPriceRule extends Model
{
    use BelongsToCompany;
    use HasValidity;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::PartyPriceRules->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'party_id',
        'item_id',
        'taxonomy_id',
        'priority',
        'discount_type',
        'discount_value',
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'taxonomy_id');
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'party_id' => ['nullable', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'item_id' => ['nullable', 'integer', 'exists:' . ERPTables::Items->value . ',id'],
            'taxonomy_id' => ['nullable', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'discount_type' => ['required', 'string', DiscountType::validationRule()],
            'discount_value' => ['required', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'party_id' => ['nullable', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'item_id' => ['nullable', 'integer', 'exists:' . ERPTables::Items->value . ',id'],
            'taxonomy_id' => ['nullable', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'discount_type' => ['sometimes', 'string', DiscountType::validationRule()],
            'discount_value' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (PartyPriceRule $rule): void {
            if (($rule->item_id === null) === ($rule->taxonomy_id === null)) {
                throw ValidationException::withMessages([
                    'item_id' => ['Exactly one of item_id or taxonomy_id is required.'],
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:4',
        ];
    }
}
