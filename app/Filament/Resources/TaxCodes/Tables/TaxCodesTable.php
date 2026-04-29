<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\TaxCodes\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class TaxCodesTable
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
                    TextColumn::make('code')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('kind')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('rate')
                        ->numeric(decimalPlaces: 4)
                        ->suffix('%')
                        ->sortable(),
                    TextColumn::make('country')
                        ->sortable(),
                    TextColumn::make('label')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('effective_from')
                        ->date()
                        ->sortable(),
                    IconColumn::make('is_active')
                        ->boolean(),
                ]);
            },
        );
    }
}
