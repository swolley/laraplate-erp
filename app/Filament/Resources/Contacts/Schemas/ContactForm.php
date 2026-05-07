<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Contacts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\ERP\Models\Party;

final class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('phone')
                    ->maxLength(255)
                    ->nullable(),
                Select::make('party_ids')
                    ->label('Parties')
                    ->multiple()
                    ->options(static fn (): array => Party::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
            ]);
    }
}
