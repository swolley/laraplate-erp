<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\DocumentSequences\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class DocumentSequencesTable
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
                    TextColumn::make('document_type')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('fiscal_year')
                        ->sortable(),
                    TextColumn::make('last_number')
                        ->sortable(),
                    IconColumn::make('gap_allowed')
                        ->boolean(),
                    TextColumn::make('prefix')
                        ->toggleable(isToggledHiddenByDefault: true),
                ]);
            },
        );
    }
}
