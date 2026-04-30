<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SalesOrders\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;

final class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $order_status_options = collect(SalesOrderStatus::cases())
            ->mapWithKeys(static fn (SalesOrderStatus $s): array => [$s->value => $s->value])
            ->all();

        $line_status_options = collect(SalesOrderLineStatus::cases())
            ->mapWithKeys(static fn (SalesOrderLineStatus $s): array => [$s->value => $s->value])
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
                    ->required(),
                Select::make('quotation_id')
                    ->relationship('quotation', 'id')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('amends_sales_order_id')
                    ->label('Amends order')
                    ->relationship('amended_from', 'reference', ignoreRecord: true)
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('reference')
                    ->maxLength(64)
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
                    ->options($order_status_options)
                    ->required()
                    ->default(SalesOrderStatus::DRAFT->value),
                Repeater::make('line_items')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('qty_ordered')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1),
                        TextInput::make('qty_delivered')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('qty_invoiced')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->nullable(),
                        Select::make('status')
                            ->options($line_status_options)
                            ->required()
                            ->default(SalesOrderLineStatus::OPEN->value),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Add line')
                    ->columnSpanFull(),
            ]);
    }
}
