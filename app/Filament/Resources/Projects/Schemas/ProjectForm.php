<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\ProjectStatus;
use Modules\ERP\Models\Quotation;

final class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        $status_options = collect(ProjectStatus::cases())
            ->mapWithKeys(static fn (ProjectStatus $status): array => [$status->value => $status->value])
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
                    ->nullable()
                    ->label('Quotation')
                    ->getOptionLabelFromRecordUsing(static function (Quotation $record): string {
                        return sprintf('#%d — %s', $record->id, $record->currency);
                    }),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options($status_options)
                    ->required()
                    ->default(ProjectStatus::ACTIVE->value),
                TextInput::make('version')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(255)
                    ->required(),
                DateTimePicker::make('valid_from')
                    ->required(),
                DateTimePicker::make('valid_to')
                    ->nullable(),
            ]);
    }
}
