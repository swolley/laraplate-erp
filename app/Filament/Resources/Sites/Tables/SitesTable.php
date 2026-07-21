<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Sites\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class SitesTable
{
    use HasTable;
    public static function configure(Table $table): Table
    {
        return self::configureTable($table, static function (Collection $columns): void {
            $columns->unshift(...[
                TextColumn::make('company.name')->label('Company')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('place.address')->label('Address')->searchable(),
                TextColumn::make('place.city')->label('City')->searchable()->sortable(),
            ]);
        });
    }
}
