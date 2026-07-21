<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRequests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class PaymentRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_id')->relationship('company', 'name')->searchable()->preload()->required()->disabledOn('edit'),
            Select::make('party_id')->relationship('party', 'name')->searchable()->preload()
                ->rules(['nullable', 'required_without:user_id', 'prohibits:user_id']),
            Select::make('user_id')->relationship('user', 'name')->searchable()->preload()
                ->rules(['nullable', 'required_without:party_id', 'prohibits:party_id']),
            TextInput::make('amount')->numeric()->minValue(0.0001)->required(),
            TextInput::make('currency')->length(3)->default('EUR')->required(),
            DatePicker::make('due_on'),
            TextInput::make('provider_code')->default('stub')->required()->disabled(),
            Textarea::make('description')->columnSpanFull(),
        ]);
    }
}
