<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Sites\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Models\Place;

final class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_id')->relationship('company', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('place_id')->relationship('place', 'address')->searchable()->preload()->required()
                ->getOptionLabelFromRecordUsing(static fn (Place $place): string => implode(', ', array_filter([
                    $place->address, $place->postcode, $place->city, $place->province, $place->country,
                ]))),
            DateTimePicker::make('valid_from')->required(),
            DateTimePicker::make('valid_to')->afterOrEqual('valid_from'),
        ]);
    }
}
