<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;

final class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        $direction_options = collect(InvoiceDirection::cases())
            ->mapWithKeys(static fn (InvoiceDirection $direction): array => [$direction->value => $direction->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('direction')
                    ->options($direction_options)
                    ->required(),
                Select::make('invoice_type')
                    ->options(collect(InvoiceType::cases())
                        ->mapWithKeys(static fn (InvoiceType $type): array => [$type->value => $type->value])
                        ->all())
                    ->required()
                    ->default(InvoiceType::Invoice->value)
                    ->live(),
                Select::make('credited_invoice_id')
                    ->label('Credited Invoice')
                    ->relationship('credited_invoice', 'reference')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(static fn (Get $get): bool => in_array($get('invoice_type'), [
                        InvoiceType::CreditNote->value,
                        InvoiceType::DebitNote->value,
                    ])),
                TextInput::make('currency')
                    ->length(3)
                    ->required()
                    ->default('EUR'),
                TextInput::make('reference')
                    ->disabled()
                    ->helperText('Assigned automatically at posting time'),
                DateTimePicker::make('posted_at')
                    ->nullable(),
                Repeater::make('line_items')
                    ->schema([
                        Select::make('sales_order_line_id')
                            ->label('Sales order line')
                            ->relationship('sales_order_line', 'id')
                            ->searchable()
                            ->preload(),
                        TextInput::make('description')
                            ->nullable(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->minValue(0.0001)
                            ->default(1)
                            ->required(),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->required(),
                        Select::make('tax_code_id')
                            ->relationship('applied_tax_code', 'code')
                            ->searchable()
                            ->preload(),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
