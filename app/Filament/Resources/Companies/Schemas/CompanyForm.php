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
