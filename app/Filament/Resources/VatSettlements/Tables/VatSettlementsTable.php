<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\VatSettlements\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class VatSettlementsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('fiscal_period.period_no')
                        ->label('Period')
                        ->sortable(),
                    TextColumn::make('vat_sales')
                        ->numeric(2),
                    TextColumn::make('vat_purchases')
                        ->numeric(2),
                    TextColumn::make('previous_credit')
                        ->numeric(2),
                    TextColumn::make('settlement_amount')
                        ->numeric(2)
                        ->color(static fn (mixed $state): string => (float) $state >= 0 ? 'danger' : 'success'),
                    TextColumn::make('status')
                        ->badge(),
                    TextColumn::make('confirmed_at')
                        ->dateTime()
                        ->sortable(),
                ]);
            },
        );
    }
}
