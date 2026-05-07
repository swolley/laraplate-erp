<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Parties\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class PartyForm
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
                Toggle::make('is_customer')
                    ->default(true),
                Toggle::make('is_supplier')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
