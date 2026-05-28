<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class BankAccountForm
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
                TextInput::make('iban')
                    ->maxLength(34),
                TextInput::make('account_no')
                    ->maxLength(64),
                TextInput::make('currency')
                    ->length(3)
                    ->default('EUR')
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
