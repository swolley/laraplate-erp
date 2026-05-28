<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankStatements\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class BankStatementsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('bank_account.name')
                        ->label('Bank account')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('period_start')
                        ->date()
                        ->sortable(),
                    TextColumn::make('period_end')
                        ->date()
                        ->sortable(),
                    TextColumn::make('lines_count')
                        ->label('Lines')
                        ->counts('lines'),
                    TextColumn::make('imported_at')
                        ->dateTime()
                        ->sortable(),
                ]);
            },
        );
    }
}
