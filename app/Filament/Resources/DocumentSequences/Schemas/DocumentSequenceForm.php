<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DocumentSequences\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\DocumentType;

final class DocumentSequenceForm
{
    public static function configure(Schema $schema): Schema
    {
        $document_type_options = collect(DocumentType::cases())
            ->mapWithKeys(static fn (DocumentType $type): array => [$type->value => $type->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                Select::make('document_type')
                    ->options($document_type_options)
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('fiscal_year')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(2100)
                    ->disabledOn('edit'),
                TextInput::make('last_number')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                Toggle::make('gap_allowed')
                    ->label('Gap allowed'),
                TextInput::make('prefix')
                    ->maxLength(32)
                    ->default(''),
                TextInput::make('padding')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(5),
                TextInput::make('format_pattern')
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('suffix')
                    ->maxLength(32)
                    ->default(''),
            ]);
    }
}
