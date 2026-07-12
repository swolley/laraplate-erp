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
                TextInput::make('tax_id')
                    ->label('Tax code')
                    ->maxLength(32),
                TextInput::make('vat_number')
                    ->label('VAT number')
                    ->maxLength(32),
                TextInput::make('fiscal_country')
                    ->length(2)
                    ->default('IT'),
                TextInput::make('address_line')
                    ->label('Address')
                    ->maxLength(255),
                TextInput::make('postal_code')
                    ->maxLength(16),
                TextInput::make('city')
                    ->maxLength(128),
                TextInput::make('province')
                    ->maxLength(8),
                TextInput::make('country')
                    ->length(2)
                    ->default('IT'),
                TextInput::make('einvoice_recipient_code')
                    ->label('SDI recipient code')
                    ->maxLength(7),
                TextInput::make('einvoice_pec_email')
                    ->label('PEC email')
                    ->email()
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
