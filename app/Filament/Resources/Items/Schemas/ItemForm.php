<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class ItemForm
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
                TextInput::make('sku')
                    ->required()
                    ->maxLength(64),
                TextInput::make('uom')
                    ->required()
                    ->maxLength(16)
                    ->default('unit'),
                Select::make('costing_method')
                    ->options([
                        'fifo' => 'fifo',
                        'weighted_avg' => 'weighted_avg',
                    ])
                    ->required()
                    ->default('fifo'),
                Select::make('taxonomy_id')
                    ->relationship('taxonomy', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }
}
