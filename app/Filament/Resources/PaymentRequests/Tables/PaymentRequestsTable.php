<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRequests\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class PaymentRequestsTable
{
    use HasTable;
    public static function configure(Table $table): Table
    {
        return self::configureTable($table, static function (Collection $columns): void {
            $columns->unshift(...[
                TextColumn::make('company.name')->label('Company')->sortable(),
                TextColumn::make('party.name')->label('Party')->placeholder('—'),
                TextColumn::make('user.name')->label('Internal user')->placeholder('—'),
                TextColumn::make('amount')->numeric(decimalPlaces: 4)->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('status')->badge(),
                TextColumn::make('checkout_url')->label('Checkout')->url(fn (?string $state): ?string => $state)->openUrlInNewTab()->limit(35),
            ]);
        });
    }
}
