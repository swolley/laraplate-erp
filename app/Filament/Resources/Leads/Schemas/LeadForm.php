<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\LeadStatus;

final class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        $status_options = collect(LeadStatus::cases())
            ->mapWithKeys(static fn (LeadStatus $s): array => [$s->value => $s->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->disabledOn('edit'),
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('contact_id')
                    ->relationship('contact', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('source')
                    ->maxLength(128)
                    ->nullable(),
                Select::make('status')
                    ->options($status_options)
                    ->required()
                    ->default(LeadStatus::NEW->value),
                Select::make('owner_user_id')
                    ->relationship('owner', 'email')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->label('Owner'),
                Textarea::make('notes')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
                DateTimePicker::make('converted_at')
                    ->nullable(),
            ]);
    }
}
