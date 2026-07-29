<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SalesOrders\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Project;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Services\Pricing\PriceResolverService;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ErpConnectionContext;

final class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $order_status_options = collect(SalesOrderStatus::cases())
            ->mapWithKeys(static fn (SalesOrderStatus $s): array => [$s->value => $s->value])
            ->all();

        $line_status_options = collect(SalesOrderLineStatus::cases())
            ->mapWithKeys(static fn (SalesOrderLineStatus $s): array => [$s->value => $s->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->options(static fn (): array => self::companyOptions())
                    ->getSearchResultsUsing(static fn (string $search): array => self::companyOptions($search))
                    ->getOptionLabelUsing(static fn (?int $value): ?string => self::companyLabel($value))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->disabledOn('edit'),
                Select::make('party_id')
                    ->options(static fn (Get $get): array => self::partyOptions((int) ($get('company_id') ?? 0)))
                    ->getSearchResultsUsing(static fn (string $search, Get $get): array => self::partyOptions(
                        (int) ($get('company_id') ?? 0),
                        $search,
                    ))
                    ->getOptionLabelUsing(static fn (?int $value, Get $get): ?string => self::partyLabel(
                        (int) ($get('company_id') ?? 0),
                        $value,
                    ))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('quotation_id')
                    ->options(static fn (Get $get): array => self::quotationOptions(
                        (int) ($get('company_id') ?? 0),
                        $get('party_id') !== null ? (int) $get('party_id') : null,
                    ))
                    ->getSearchResultsUsing(static fn (string $search, Get $get): array => self::quotationOptions(
                        (int) ($get('company_id') ?? 0),
                        $get('party_id') !== null ? (int) $get('party_id') : null,
                        $search,
                    ))
                    ->getOptionLabelUsing(static fn (?int $value, Get $get): ?string => self::quotationLabel(
                        (int) ($get('company_id') ?? 0),
                        $value,
                    ))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('project_id')
                    ->options(static fn (Get $get): array => self::projectOptions(
                        (int) ($get('company_id') ?? 0),
                        $get('party_id') !== null ? (int) $get('party_id') : null,
                    ))
                    ->getSearchResultsUsing(static fn (string $search, Get $get): array => self::projectOptions(
                        (int) ($get('company_id') ?? 0),
                        $get('party_id') !== null ? (int) $get('party_id') : null,
                        $search,
                    ))
                    ->getOptionLabelUsing(static fn (?int $value, Get $get): ?string => self::projectLabel(
                        (int) ($get('company_id') ?? 0),
                        $value,
                    ))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('amends_sales_order_id')
                    ->label('Amends order')
                    ->options(static fn (Get $get, ?Model $record): array => self::salesOrderOptions(
                        (int) ($get('company_id') ?? 0),
                        exclude_id: self::salesOrderRecordId($record),
                    ))
                    ->getSearchResultsUsing(static fn (string $search, Get $get, ?Model $record): array => self::salesOrderOptions(
                        (int) ($get('company_id') ?? 0),
                        search: $search,
                        exclude_id: self::salesOrderRecordId($record),
                    ))
                    ->getOptionLabelUsing(static fn (?int $value, Get $get): ?string => self::salesOrderLabel(
                        (int) ($get('company_id') ?? 0),
                        $value,
                    ))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('reference')
                    ->maxLength(64)
                    ->nullable(),
                TextInput::make('currency')
                    ->length(3)
                    ->default('EUR')
                    ->required(),
                Textarea::make('notes')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options($order_status_options)
                    ->required()
                    ->default(SalesOrderStatus::Draft->value),
                Repeater::make('line_items')
                    ->schema([
                        Select::make('item_id')
                            ->options(static fn (Get $get): array => self::itemOptions((int) ($get('../../company_id') ?? 0)))
                            ->getSearchResultsUsing(static fn (string $search, Get $get): array => self::itemOptions(
                                (int) ($get('../../company_id') ?? 0),
                                $search,
                            ))
                            ->getOptionLabelUsing(static fn (?int $value, Get $get): ?string => self::itemLabel(
                                (int) ($get('../../company_id') ?? 0),
                                $value,
                            ))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(static fn (Get $get, Set $set, ?int $state): null => self::applyResolvedPrice($get, $set, $state)),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('qty_ordered')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0.0001),
                        TextInput::make('qty_delivered')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('qty_invoiced')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->nullable(),
                        Select::make('status')
                            ->options($line_status_options)
                            ->required()
                            ->default(SalesOrderLineStatus::Open->value),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Add line')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function companyOptions(?string $search = null): array
    {
        return self::applySearch(self::companyQuery(), $search, ['name'])
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    private static function companyLabel(?int $company_id): ?string
    {
        if ($company_id === null) {
            return null;
        }

        return self::companyQuery()->whereKey($company_id)->value('name');
    }

    /**
     * @return array<int, string>
     */
    private static function partyOptions(int $company_id, ?string $search = null): array
    {
        $models = self::modelsForCompany($company_id);

        if (! $models instanceof ConnectionScopedModels) {
            return [];
        }

        return self::applySearch(
            $models->query(Party::class)
                ->where('company_id', $company_id)
                ->customers(),
            $search,
            ['name'],
        )
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    private static function partyLabel(int $company_id, ?int $party_id): ?string
    {
        if ($party_id === null) {
            return null;
        }

        return self::modelsForCompany($company_id)?->query(Party::class)
            ->where('company_id', $company_id)
            ->whereKey($party_id)
            ->value('name');
    }

    /**
     * @return array<int, string>
     */
    private static function itemOptions(int $company_id, ?string $search = null): array
    {
        $models = self::modelsForCompany($company_id);

        if (! $models instanceof ConnectionScopedModels) {
            return [];
        }

        return self::applySearch(
            $models->query(Item::class)->where('company_id', $company_id),
            $search,
            ['name', 'sku'],
        )
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(static fn (Item $item): array => [
                (int) $item->getKey() => $item->name . ' (' . $item->sku . ')',
            ])
            ->all();
    }

    private static function itemLabel(int $company_id, ?int $item_id): ?string
    {
        if ($item_id === null) {
            return null;
        }

        $item = self::modelsForCompany($company_id)?->query(Item::class)
            ->where('company_id', $company_id)
            ->find($item_id);

        return $item instanceof Item ? $item->name . ' (' . $item->sku . ')' : null;
    }

    /**
     * @return array<int, string>
     */
    private static function quotationOptions(int $company_id, ?int $party_id, ?string $search = null): array
    {
        $search = filled($search) ? mb_trim((string) $search) : null;

        if ($search !== null && filter_var($search, FILTER_VALIDATE_INT) === false) {
            return [];
        }

        $models = self::modelsForCompany($company_id);

        if (! $models instanceof ConnectionScopedModels) {
            return [];
        }

        return $models->query(Quotation::class)
            ->where('company_id', $company_id)
            ->when($party_id !== null, static fn (Builder $query): Builder => $query->where('party_id', $party_id))
            ->when($search !== null, static fn (Builder $query): Builder => $query->whereKey((int) $search))
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('id', 'id')
            ->mapWithKeys(static fn (int|string $id): array => [(int) $id => '#' . $id])
            ->all();
    }

    private static function quotationLabel(int $company_id, ?int $quotation_id): ?string
    {
        if ($quotation_id === null) {
            return null;
        }

        $exists = self::modelsForCompany($company_id)?->query(Quotation::class)
            ->where('company_id', $company_id)
            ->whereKey($quotation_id)
            ->exists();

        return $exists ? '#' . $quotation_id : null;
    }

    /**
     * @return array<int, string>
     */
    private static function projectOptions(int $company_id, ?int $party_id, ?string $search = null): array
    {
        $models = self::modelsForCompany($company_id);

        if (! $models instanceof ConnectionScopedModels) {
            return [];
        }

        return self::applySearch(
            $models->query(Project::class)
                ->where('company_id', $company_id)
                ->when($party_id !== null, static fn (Builder $query): Builder => $query->where('party_id', $party_id)),
            $search,
            ['name'],
        )
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    private static function projectLabel(int $company_id, ?int $project_id): ?string
    {
        if ($project_id === null) {
            return null;
        }

        return self::modelsForCompany($company_id)?->query(Project::class)
            ->where('company_id', $company_id)
            ->whereKey($project_id)
            ->value('name');
    }

    /**
     * @return array<int, string>
     */
    private static function salesOrderOptions(
        int $company_id,
        ?string $search = null,
        ?int $exclude_id = null,
    ): array {
        $models = self::modelsForCompany($company_id);

        if (! $models instanceof ConnectionScopedModels) {
            return [];
        }

        return self::applySearch(
            $models->query(SalesOrder::class)
                ->where('company_id', $company_id)
                ->when($exclude_id !== null, static fn (Builder $query): Builder => $query->whereKeyNot($exclude_id)),
            $search,
            ['reference'],
        )
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->mapWithKeys(static fn (SalesOrder $order): array => [
                (int) $order->getKey() => filled($order->reference)
                    ? (string) $order->reference
                    : '#' . $order->getKey(),
            ])
            ->all();
    }

    private static function salesOrderLabel(int $company_id, ?int $sales_order_id): ?string
    {
        if ($sales_order_id === null) {
            return null;
        }

        $order = self::modelsForCompany($company_id)?->query(SalesOrder::class)
            ->where('company_id', $company_id)
            ->find($sales_order_id);

        if (! $order instanceof SalesOrder) {
            return null;
        }

        return filled($order->reference) ? (string) $order->reference : '#' . $order->getKey();
    }

    private static function salesOrderRecordId(?Model $record): ?int
    {
        if (! $record instanceof SalesOrder || ! $record->exists) {
            return null;
        }

        return (int) $record->getKey();
    }

    /**
     * @return Builder<Company>
     */
    private static function companyQuery(): Builder
    {
        return app(ErpConnectionContext::class)
            ->model(Company::class)
            ->newQuery();
    }

    private static function modelsForCompany(int $company_id): ?ConnectionScopedModels
    {
        if ($company_id === 0) {
            return null;
        }

        $company = self::companyQuery()->find($company_id);

        return $company instanceof Company ? ConnectionScopedModels::for($company) : null;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $columns
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    private static function applySearch(Builder $query, ?string $search, array $columns): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(static function (Builder $query) use ($columns, $search): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($column, 'like', '%' . $search . '%');
            }
        });
    }

    private static function applyResolvedPrice(Get $get, Set $set, ?int $item_id): null
    {
        if ($item_id === null || filled($get('unit_price'))) {
            return null;
        }

        $company_id = $get('../../company_id');

        if ($company_id === null) {
            return null;
        }

        $company = self::companyQuery()->find((int) $company_id);
        $item = $company instanceof Company
            ? ConnectionScopedModels::for($company)->query(Item::class)
                ->where('company_id', $company->getKey())
                ->find($item_id)
            : null;

        if (! $company instanceof Company || ! $item instanceof Item) {
            return null;
        }

        try {
            $result = app(PriceResolverService::class)->resolve(
                company: $company,
                item: $item,
                party_id: $get('../../party_id') !== null ? (int) $get('../../party_id') : null,
                currency: (string) ($get('../../currency') ?? 'EUR'),
            );
        } catch (\Illuminate\Validation\ValidationException) {
            return null;
        }

        $set('unit_price', $result->resolvedUnitPrice);

        return null;
    }
}
