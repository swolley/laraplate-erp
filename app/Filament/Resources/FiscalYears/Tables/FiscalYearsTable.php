<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalYears\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class FiscalYearsTable
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
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('year')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('start_date')
                        ->date()
                        ->sortable(),
                    TextColumn::make('end_date')
                        ->date()
                        ->sortable(),
                    IconColumn::make('is_closed')
                        ->boolean(),
                ]);
            },
        );
    }
}
