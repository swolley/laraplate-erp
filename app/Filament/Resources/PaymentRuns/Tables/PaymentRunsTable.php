<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRuns\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class PaymentRunsTable
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
                        ->sortable(),
                    TextColumn::make('bank_account.name')
                        ->label('Bank')
                        ->sortable(),
                    TextColumn::make('execution_date')
                        ->date()
                        ->sortable(),
                    TextColumn::make('status')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('total_amount_doc')
                        ->numeric(4)
                        ->sortable(),
                    TextColumn::make('currency'),
                ]);
            },
        );
    }
}
