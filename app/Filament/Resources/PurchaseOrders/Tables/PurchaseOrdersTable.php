<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PurchaseOrders\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class PurchaseOrdersTable
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
                    TextColumn::make('customer.name')
                        ->label('Customer')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('reference')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('status')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('lines_count')
                        ->label('Lines')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('lines_sum_qty_ordered')
                        ->label('Σ Qty ordered')
                        ->numeric()
                        ->sortable()
                        ->default(0),
                    TextColumn::make('lines_sum_qty_received')
                        ->label('Σ Qty received')
                        ->numeric()
                        ->sortable()
                        ->default(0),
                    TextColumn::make('ordered_at')
                        ->dateTime()
                        ->sortable(),
                ]);
            },
        );
    }
}
