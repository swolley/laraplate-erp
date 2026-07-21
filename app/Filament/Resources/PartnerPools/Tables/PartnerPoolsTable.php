<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PartnerPools\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class PartnerPoolsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable($table, static function (Collection $columns): void {
            $columns->unshift(...[
                TextColumn::make('company.name')->label('Company')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('currency')->sortable(),
                TextColumn::make('members_count')->counts('members')->label('Members'),
                IconColumn::make('is_active')->boolean(),
            ]);
        });
    }
}
