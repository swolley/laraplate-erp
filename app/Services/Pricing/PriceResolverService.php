<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Pricing;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DiscountType;
use Modules\ERP\Data\Pricing\PriceResolutionResult;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\PartyPriceRule;
use Modules\ERP\Models\PriceList;
use Modules\ERP\Models\PriceListItem;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Support\Decimal;

final class PriceResolverService
{
    public function resolve(
        Company $company,
        Item $item,
        ?int $party_id = null,
        string $currency = 'EUR',
        ?CarbonInterface $date = null,
    ): PriceResolutionResult {
        $date ??= Date::now();
        ConnectionScopedTransaction::connection($company, $item);
        $models = ConnectionScopedModels::for($company, $item);
        $company_id = (int) $company->getKey();

        if ((int) $item->company_id !== $company_id) {
            throw ValidationException::withMessages([
                'item_id' => ['The item does not exist for the selected company.'],
            ]);
        }

        $price_lists = $models->query(PriceList::class)
            ->select('id')
            ->where('company_id', $company_id)
            ->where('currency', $currency)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });

        $base_query = $models->query(PriceListItem::class)
            ->whereIn('price_list_id', $price_lists)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });

        /** @var PriceListItem|null $price_list_item */
        $price_list_item = (clone $base_query)
            ->where('item_id', $item->id)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();

        if ($price_list_item === null && $item->taxonomy_id !== null) {
            $price_list_item = (clone $base_query)
                ->whereNull('item_id')
                ->where('taxonomy_id', $item->taxonomy_id)
                ->orderByDesc('valid_from')
                ->orderByDesc('id')
                ->first();
        }

        if ($price_list_item === null) {
            throw ValidationException::withMessages([
                'item_id' => ['No active direct or taxonomy price list item matches the item.'],
            ]);
        }

        $rule = $this->resolveRule($models, $company_id, $item, $party_id, $date);
        $base_price = $price_list_item->unit_price;

        return new PriceResolutionResult(
            priceListItem: $price_list_item,
            baseUnitPrice: $base_price,
            resolvedUnitPrice: $this->applyRule($base_price, $rule),
            appliedRule: $rule,
        );
    }

    private function resolveRule(
        ConnectionScopedModels $models,
        int $company_id,
        Item $item,
        ?int $party_id,
        CarbonInterface $date,
    ): ?PartyPriceRule
    {
        /** @var PartyPriceRule|null $rule */
        $rule = $models->query(PartyPriceRule::class)
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
