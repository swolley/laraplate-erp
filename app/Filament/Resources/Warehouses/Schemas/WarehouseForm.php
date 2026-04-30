<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class WarehouseForm
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
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->maxLength(32),
                Select::make('site_id')
                    ->relationship('site', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }
}
