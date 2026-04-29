<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Companies\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class CompaniesTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('slug')
                        ->searchable()
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('legal_name')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('tax_id')
                        ->label('Tax ID')
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('fiscal_country')
                        ->sortable(),
                    TextColumn::make('default_currency')
                        ->sortable(),
                    IconColumn::make('is_default')
                        ->boolean()
                        ->label('Default'),
                ]);
            },
        );
    }
}
