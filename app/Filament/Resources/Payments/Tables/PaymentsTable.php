<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Payments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class PaymentsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('reference')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('party.name')
                        ->label('Party')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('direction')
                        ->sortable(),
                    TextColumn::make('payment_date')
                        ->date()
                        ->sortable(),
                    TextColumn::make('amount_doc')
                        ->numeric(4)
                        ->sortable(),
                    TextColumn::make('currency_doc'),
                ]);
            },
        );
    }
}
