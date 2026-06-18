<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\ReturnOrders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Casts\ReturnStatus;

final class ReturnOrderForm
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
                Select::make('party_id')
                    ->relationship(
                        'party',
                        'name',
                        modifyQueryUsing: static fn (Builder $query): Builder => $query->customers(),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('invoice_id')
                    ->relationship('invoice', 'reference')
                    ->searchable()
                    ->preload(),
                Select::make('credit_note_invoice_id')
                    ->relationship('credit_note_invoice', 'reference')
                    ->searchable()
                    ->preload()
                    ->disabled(),
                TextInput::make('reference')
                    ->maxLength(64),
                Select::make('status')
                    ->options(collect(ReturnStatus::cases())->mapWithKeys(
                        static fn (ReturnStatus $status): array => [$status->value => $status->value],
                    )->all())
                    ->default(ReturnStatus::Draft->value)
                    ->required()
                    ->disabledOn('edit'),
                DateTimePicker::make('processed_at')
                    ->disabled(),
                Repeater::make('lines')
                    ->relationship()
                    ->schema([
                        Select::make('item_id')
                            ->relationship('item', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->minValue(0.0001)
                            ->required(),
                        TextInput::make('unit_cost')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
