<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentTerms\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class PaymentTermsTable
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
                    TextColumn::make('company.name')
                        ->label('Company')
                        ->sortable(),
                    IconColumn::make('is_active')
                        ->boolean(),
                    TextColumn::make('rate_lines')
                        ->formatStateUsing(static fn (mixed $state): string => count((array) $state) . ' installments'),
                ]);
            },
        );
    }
}
