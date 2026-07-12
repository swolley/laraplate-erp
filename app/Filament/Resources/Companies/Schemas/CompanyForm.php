<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('legal_name')
                    ->maxLength(255),
                TextInput::make('tax_id')
                    ->maxLength(32)
                    ->label('Tax ID / VAT'),
                TextInput::make('fiscal_country')
                    ->required()
                    ->length(2)
                    ->default('IT')
                    ->placeholder('IT'),
                TextInput::make('fiscal_regime')
                    ->label('Fiscal regime')
                    ->maxLength(4)
                    ->placeholder('RF01'),
                TextInput::make('legal_address_line')
                    ->label('Legal address')
                    ->maxLength(255),
                TextInput::make('legal_postal_code')
                    ->label('Legal postal code')
                    ->maxLength(16),
                TextInput::make('legal_city')
                    ->label('Legal city')
                    ->maxLength(128),
                TextInput::make('legal_province')
                    ->label('Legal province')
                    ->maxLength(8),
                TextInput::make('legal_country')
                    ->label('Legal country')
                    ->length(2)
                    ->default('IT'),
                TextInput::make('rea_office')
                    ->label('REA office')
                    ->maxLength(8),
                TextInput::make('rea_number')
                    ->label('REA number')
                    ->maxLength(32),
                TextInput::make('share_capital')
                    ->numeric()
                    ->minValue(0),
                Toggle::make('sole_shareholder')
                    ->label('Sole shareholder'),
                TextInput::make('liquidation_status')
                    ->label('Liquidation status')
                    ->maxLength(2),
                TextInput::make('default_currency')
                    ->required()
                    ->length(3)
                    ->default('EUR')
                    ->placeholder('EUR'),
                Toggle::make('is_default')
                    ->label('Default tenant'),
                KeyValue::make('settings')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->addActionLabel('Add setting')
                    ->nullable(),
            ]);
    }
}
