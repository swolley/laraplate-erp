<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PriceLists\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class PriceListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('currency')
                    ->length(3)
                    ->default('EUR')
                    ->required(),
                DatePicker::make('valid_from'),
                DatePicker::make('valid_to'),
                Repeater::make('price_list_items')
                    ->relationship('price_list_items')
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->relationship('item', 'name')
                            ->searchable()
                            ->preload()
                            ->rules(['nullable', 'required_without:taxonomy_id', 'prohibits:taxonomy_id']),
                        Select::make('taxonomy_id')
                            ->label('Activity')
                            ->relationship('taxonomy', 'name')
                            ->searchable()
                            ->preload()
                            ->rules(['nullable', 'required_without:item_id', 'prohibits:item_id']),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('uom')
                            ->label('UOM')
                            ->maxLength(64),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        DatePicker::make('valid_from'),
                        DatePicker::make('valid_to'),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Add price item')
                    ->columnSpanFull(),
            ]);
    }
}
