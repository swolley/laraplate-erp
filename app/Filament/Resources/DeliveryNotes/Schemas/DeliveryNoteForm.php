<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DeliveryNotes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                Textarea::make('notes')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
