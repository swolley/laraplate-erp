<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Casts\PurchaseOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ErpConnectionContext;

final class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $status_options = collect(PurchaseOrderStatus::cases())
            ->mapWithKeys(static fn (PurchaseOrderStatus $status): array => [$status->value => $status->value])
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
                    ->required(),
                TextInput::make('reference')
                    ->maxLength(64)
                    ->nullable(),
                TextInput::make('currency')
                    ->length(3)
                    ->required()
                    ->default('EUR'),
                Select::make('status')
                    ->options($status_options)
                    ->required()
                    ->default(PurchaseOrderStatus::Draft->value),
                DateTimePicker::make('ordered_at')
                    ->nullable(),
                Repeater::make('line_items')
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->searchable()
                            ->nullable()
                            ->getSearchResultsUsing(static function (string $search, Get $get): array {
                                $company_id = (int) $get('../../company_id');

                                if ($company_id === 0) {
                                    return [];
                                }

                                return self::itemOptions($company_id, $search);
                            })
                            ->getOptionLabelUsing(static fn (?int $value, Get $get): ?string => self::itemLabel(
                                (int) ($get('../../company_id') ?? 0),
                                $value,
                            )),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('qty_ordered')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0.0001),
                        TextInput::make('qty_received')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->nullable(),
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
                ->suppliers(),
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

        $models = self::modelsForCompany($company_id);

        return $models?->query(Party::class)
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

        $models = self::modelsForCompany($company_id);
        $item = $models?->query(Item::class)
            ->where('company_id', $company_id)
            ->find($item_id);

        return $item instanceof Item ? $item->name . ' (' . $item->sku . ')' : null;
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
}
