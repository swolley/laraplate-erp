<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Opportunities\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\OpportunityStatus;

final class OpportunityForm
{
    public static function configure(Schema $schema): Schema
    {
        $status_options = collect(OpportunityStatus::cases())
            ->mapWithKeys(static fn (OpportunityStatus $s): array => [$s->value => $s->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                Select::make('lead_id')
                    ->relationship('lead', 'title')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('stage_taxonomy_id')
                    ->label('Stage')
                    ->relationship('stage', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Pipeline stages require taxonomies: run ERPDatabaseSeeder then DevERPOpportunityStagesTaxonomySeeder (or equivalent) so stages exist.'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->options($status_options)
                    ->required()
                    ->default(OpportunityStatus::OPEN->value),
                DatePicker::make('expected_close_date')
                    ->nullable(),
                TextInput::make('expected_value_doc')
                    ->numeric()
                    ->nullable(),
                TextInput::make('expected_currency_doc')
                    ->length(3)
                    ->default('EUR')
                    ->required(),
                TextInput::make('expected_value_local')
                    ->numeric()
                    ->nullable(),
                TextInput::make('expected_currency_local')
                    ->length(3)
                    ->default('EUR')
                    ->required(),
                TextInput::make('expected_fx_rate')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('probability')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->nullable(),
                DateTimePicker::make('won_at')
                    ->nullable(),
                DateTimePicker::make('lost_at')
                    ->nullable(),
                Textarea::make('lost_reason')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
