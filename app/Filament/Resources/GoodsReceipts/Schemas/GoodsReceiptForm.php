<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\GoodsReceipts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                Textarea::make('notes')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
