<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\StockLevels\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class StockLevelsTable
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
                        ->toggleable(),
                    TextColumn::make('item.name')
                        ->label('Item')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('warehouse.name')
                        ->label('Warehouse')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('quantity')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('weighted_avg_cost')
                        ->numeric(decimalPlaces: 4)
                        ->sortable(),
                ]);
            },
        );
    }
}
