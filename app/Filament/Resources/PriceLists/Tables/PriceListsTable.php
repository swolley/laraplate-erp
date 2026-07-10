<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PriceLists\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class PriceListsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('company.name')
                        ->label('Company')
                        ->searchable()
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('currency')
                        ->sortable(),
                    TextColumn::make('price_list_items_count')
                        ->label('Items')
                        ->sortable(),
                ]);
            },
        );
    }
}
