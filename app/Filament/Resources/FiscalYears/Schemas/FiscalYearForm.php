<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\FiscalYears\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class FiscalYearForm
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
                TextInput::make('year')
                    ->required()
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue(2100),
                DatePicker::make('start_date')
                    ->required()
                    ->native(false),
                DatePicker::make('end_date')
                    ->required()
                    ->native(false),
                Toggle::make('is_closed')
                    ->label('Closed'),
            ]);
    }
}
