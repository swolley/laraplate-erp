<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Quotations\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class QuotationsTable
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
                    TextColumn::make('customer.name')
                        ->label('Customer')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('opportunity.name')
                        ->label('Opportunity')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('currency')
                        ->sortable(),
                    TextColumn::make('status')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('version')
                        ->sortable(),
                ]);
            },
        );
    }
}
