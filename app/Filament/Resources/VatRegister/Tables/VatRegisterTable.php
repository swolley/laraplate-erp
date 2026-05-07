<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\VatRegister\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class VatRegisterTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('protocol_number')
                        ->sortable(),
                    TextColumn::make('register_type')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('invoice.reference')
                        ->label('Invoice')
                        ->searchable(),
                    TextColumn::make('tax_code.code')
                        ->label('Tax Code'),
                    TextColumn::make('registration_date')
                        ->date()
                        ->sortable(),
                    TextColumn::make('taxable_amount')
                        ->numeric(2)
                        ->sortable(),
                    TextColumn::make('tax_amount')
                        ->numeric(2)
                        ->sortable(),
                    TextColumn::make('fiscal_year.year')
                        ->label('Year')
                        ->sortable(),
                ]);
            },
        );
    }
}
