<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Pricing;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DiscountType;
use Modules\ERP\Data\Pricing\PriceResolutionResult;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\PartyPriceRule;
use Modules\ERP\Models\PriceListItem;
use Modules\ERP\Support\Decimal;

final class PriceResolverService
{
    public function resolve(
        int $company_id,
        int $item_id,
        ?int $party_id = null,
        string $currency = 'EUR',
        ?CarbonInterface $date = null,
    ): PriceResolutionResult {
        $date ??= Date::now();

        /** @var Item|null $item */
        $item = Item::query()
            ->whereKey($item_id)
            ->where('company_id', $company_id)
            ->first();

        if ($item === null || $item->taxonomy_id === null) {
            throw ValidationException::withMessages([
                'item_id' => ['The item is missing or has no pricing taxonomy.'],
            ]);
        }

        /** @var PriceListItem|null $price_list_item */
        $price_list_item = PriceListItem::query()
            ->where('taxonomy_id', $item->taxonomy_id)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            })
            ->whereHas('price_list', function (Builder $query) use ($company_id, $currency, $date): void {
                $query->where('company_id', $company_id)
                    ->where('currency', $currency)
                    ->where(function (Builder $query) use ($date): void {
                        $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
                    })
                    ->where(function (Builder $query) use ($date): void {
                        $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
                    });
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();

        if ($price_list_item === null) {
            throw ValidationException::withMessages([
                'item_id' => ['No active price list item matches the item taxonomy.'],
            ]);
        }

        $rule = $this->resolveRule($company_id, $item, $party_id, $date);
        $base_price = $price_list_item->unit_price;

        return new PriceResolutionResult(
            priceListItem: $price_list_item,
            baseUnitPrice: $base_price,
            resolvedUnitPrice: $this->applyRule($base_price, $rule),
            appliedRule: $rule,
        );
    }

    private function resolveRule(int $company_id, Item $item, ?int $party_id, CarbonInterface $date): ?PartyPriceRule
    {
        /** @var PartyPriceRule|null $rule */
        $rule = PartyPriceRule::query()
            ->where('company_id', $company_id)
            ->where(function (Builder $query) use ($party_id): void {
                $query->whereNull('party_id');

                if ($party_id !== null) {
                    $query->orWhere('party_id', $party_id);
                }
            })
            ->where(function (Builder $query) use ($item): void {
                $query->where('item_id', $item->id);

                if ($item->taxonomy_id !== null) {
                    $query->orWhere('taxonomy_id', $item->taxonomy_id);
                }
            })
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            })
            ->orderByDesc('party_id')
            ->orderByDesc('item_id')
            ->orderBy('priority')
            ->orderByDesc('id')
            ->first();

        return $rule;
    }

    private function applyRule(string $base_price, ?PartyPriceRule $rule): string
    {
        if ($rule === null) {
            return Decimal::format($base_price);
        }

        $value = (string) $rule->discount_value;

        $resolved = match ($rule->discount_type) {
            DiscountType::Percent => Decimal::mul($base_price, Decimal::sub('1', Decimal::div($value, '100'))),
            DiscountType::FixedAmount => Decimal::sub($base_price, $value),
            DiscountType::OverridePrice => Decimal::format($value),
        };

        return Decimal::isNegative($resolved) ? '0.0000' : $resolved;
    }
}
