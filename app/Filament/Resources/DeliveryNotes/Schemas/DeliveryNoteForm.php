<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DeliveryNotes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\Warehouse;

final class DeliveryNoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('sales_order_id')
                    ->relationship('sales_order', 'reference')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('reference')
                    ->maxLength(64)
                    ->nullable(),
                DateTimePicker::make('delivered_at')
                    ->nullable(),
                Repeater::make('line_items')
                    ->schema([
                        Select::make('item_id')
                            ->options(static fn (): array => Item::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        Select::make('warehouse_id')
                            ->options(static fn (): array => Warehouse::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1),
                        Select::make('sales_order_line_id')
                            ->options(static fn (): array => SalesOrderLine::query()
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(static fn (SalesOrderLine $line): array => [
                                    (string) $line->id => trim("{$line->id} - {$line->name}"),
                                ])
                                ->all())
                            ->searchable()
                            ->nullable(),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Add line')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
