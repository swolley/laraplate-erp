<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Invoices\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class InvoicesTable
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
                    TextColumn::make('direction')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('currency')
                        ->sortable(),
                    TextColumn::make('posted_at')
                        ->dateTime()
                        ->sortable(),
                ]);
            },
        );
    }
}
