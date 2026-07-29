<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankStatements\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;

final class BankStatementForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit')
                    ->live(),
                Select::make('bank_account_id')
                    ->relationship('bank_account', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('period_start'),
                DatePicker::make('period_end'),
                TextInput::make('source_filename')
                    ->maxLength(255),
            ]);
    }
}
