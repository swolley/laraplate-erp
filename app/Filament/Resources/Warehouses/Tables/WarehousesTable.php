<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Warehouses\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class WarehousesTable
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
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('code')
                        ->searchable()
                        ->sortable(),
                ]);
            },
        );
    }
}
