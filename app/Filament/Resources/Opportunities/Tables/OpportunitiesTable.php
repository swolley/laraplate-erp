<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Opportunities\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class OpportunitiesTable
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
                    TextColumn::make('status')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('stage.name')
                        ->label('Stage')
                        ->toggleable(),
                    TextColumn::make('customer.name')
                        ->label('Customer')
                        ->toggleable(),
                    TextColumn::make('expected_close_date')
                        ->date()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]);
            },
        );
    }
}
