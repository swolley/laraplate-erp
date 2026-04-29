<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Accounts\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class AccountsTable
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
                        ->searchable()
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('code')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('kind')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('parent.code')
                        ->label('Parent code')
                        ->toggleable(isToggledHiddenByDefault: true),
                    IconColumn::make('is_active')
                        ->boolean(),
                ]);
            },
        );
    }
}
