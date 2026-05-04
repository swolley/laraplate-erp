<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\GoodsReceipts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\Warehouse;

final class GoodsReceiptForm
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
                Select::make('purchase_order_id')
                    ->relationship('purchase_order', 'reference')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('reference')
                    ->maxLength(64)
                    ->nullable(),
                DateTimePicker::make('received_at')
                    ->nullable(),
                DateTimePicker::make('posted_at')
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
                        TextInput::make('unit_cost')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0),
                        Select::make('purchase_order_line_id')
                            ->options(static fn (): array => PurchaseOrderLine::query()
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(static fn (PurchaseOrderLine $line): array => [
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
