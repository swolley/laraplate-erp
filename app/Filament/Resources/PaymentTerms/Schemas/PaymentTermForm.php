<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentTerms\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class PaymentTermForm
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
                    ->maxLength(128),
                Textarea::make('description')
                    ->nullable()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true),
                Repeater::make('rate_lines')
                    ->schema([
                        TextInput::make('days')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        TextInput::make('percent')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(100),
                        TextInput::make('payment_method')
                            ->nullable()
                            ->maxLength(64)
                            ->helperText('e.g. bank_transfer, check'),
                    ])
                    ->minItems(1)
                    ->addActionLabel('Add installment')
                    ->columnSpanFull(),
            ]);
    }
}
