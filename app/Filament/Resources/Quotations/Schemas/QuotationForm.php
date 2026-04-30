<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Quotations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\BillingMode;
use Modules\ERP\Casts\QuoteStatus;

final class QuotationForm
{
    public static function configure(Schema $schema): Schema
    {
        $status_options = collect(QuoteStatus::cases())
            ->mapWithKeys(static fn (QuoteStatus $status): array => [$status->value => $status->value])
            ->all();

        $billing_mode_options = collect(BillingMode::cases())
            ->mapWithKeys(static fn (BillingMode $mode): array => [$mode->value => $mode->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('opportunity_id')
                    ->label('Opportunity')
                    ->relationship(
                        'opportunity',
                        'name',
                        modifyQueryUsing: static function ($query, $search, Get $get) {
                            $customer_id = (int) ($get('customer_id') ?? 0);

                            if ($customer_id === 0) {
                                return $query->whereRaw('0 = 1');
                            }

                            return $query->where('opportunities.customer_id', $customer_id);
                        },
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('currency')
                    ->length(3)
                    ->default('EUR')
                    ->required(),
                Textarea::make('notes')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options($status_options)
                    ->required()
                    ->default(QuoteStatus::DRAFT->value),
                TextInput::make('version')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(255)
                    ->required(),
                DateTimePicker::make('valid_from')
                    ->nullable(),
                DateTimePicker::make('valid_to')
                    ->nullable(),
                Repeater::make('line_items')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('billing_mode')
                            ->options($billing_mode_options)
                            ->required()
                            ->default(BillingMode::UNIT->value),
                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(65535),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->nullable(),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Add line')
                    ->columnSpanFull(),
            ]);
    }
}
