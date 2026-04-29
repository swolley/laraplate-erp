<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\TaxCodes\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\TaxKind;

final class TaxCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        $kind_options = collect(TaxKind::cases())
            ->mapWithKeys(static fn (TaxKind $kind): array => [$kind->value => $kind->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('code')
                    ->required()
                    ->maxLength(64)
                    ->visibleOn('create'),
                Select::make('kind')
                    ->options($kind_options)
                    ->required()
                    ->visibleOn('create'),
                TextInput::make('country')
                    ->required()
                    ->length(2)
                    ->default('IT')
                    ->visibleOn('create'),
                TextInput::make('rate')
                    ->required()
                    ->numeric()
                    ->suffix('%')
                    ->visibleOn('create'),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255)
                    ->visibleOn('create'),
                DatePicker::make('effective_from')
                    ->required()
                    ->native(false)
                    ->visibleOn('create'),
                Toggle::make('is_active')
                    ->default(true),
                Select::make('replaced_by_tax_code_id')
                    ->relationship('replaced_by', 'code')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->label('Superseding tax code'),
                KeyValue::make('meta')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->addActionLabel('Add meta')
                    ->nullable(),
            ]);
    }
}
