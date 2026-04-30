<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class PurchaseOrderForm
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
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('reference')
                    ->maxLength(64)
                    ->nullable(),
                TextInput::make('currency')
                    ->length(3)
                    ->required()
                    ->default('EUR'),
                TextInput::make('status')
                    ->required()
                    ->maxLength(32)
                    ->default('draft'),
                DateTimePicker::make('ordered_at')
                    ->nullable(),
            ]);
    }
}
