<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\StockLevels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class StockLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('item_id')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('weighted_avg_cost')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
