<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\PaymentDirection;

final class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        $direction_options = collect(PaymentDirection::cases())
            ->mapWithKeys(static fn (PaymentDirection $direction): array => [$direction->value => $direction->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit')
                    ->live(),
                Select::make('party_id')
                    ->relationship('party', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('direction')
                    ->options($direction_options)
                    ->required(),
                DatePicker::make('payment_date')
                    ->required()
                    ->default(now()),
                TextInput::make('amount_doc')
                    ->numeric()
                    ->required()
                    ->minValue(0.0001),
                TextInput::make('currency_doc')
                    ->length(3)
                    ->default('EUR')
                    ->required(),
                TextInput::make('amount_local')
                    ->numeric()
                    ->required(),
                TextInput::make('fx_rate')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('reference')
                    ->nullable()
                    ->maxLength(64),
                Textarea::make('notes')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
