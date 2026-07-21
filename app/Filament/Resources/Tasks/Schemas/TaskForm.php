<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Tasks\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

final class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('project_id')->relationship('project', 'name')->searchable()->preload(),
            Select::make('site_id')->relationship('site', 'name')->searchable()->preload(),
            Select::make('taxonomy_id')->relationship('taxonomy', 'name')->label('Activity')->searchable()->preload()->required(),
            DateTimePicker::make('valid_from')->label('Starts at')->required(),
            DateTimePicker::make('valid_to')->label('Ends at')->afterOrEqual('valid_from'),
        ]);
    }
}
