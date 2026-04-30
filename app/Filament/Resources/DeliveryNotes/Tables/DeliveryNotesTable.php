<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DeliveryNotes\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class DeliveryNotesTable
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
                    TextColumn::make('reference')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('sales_order.reference')
                        ->label('Sales order')
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('delivered_at')
                        ->dateTime()
                        ->sortable(),
                ]);
            },
        );
    }
}
