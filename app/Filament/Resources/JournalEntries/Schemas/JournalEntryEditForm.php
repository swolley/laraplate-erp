<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;

final class JournalEntryEditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...JournalEntryHeaderFields::components(company_locked: true),
                Repeater::make('lines')
                    ->relationship()
                    ->schema(JournalEntryLineSchema::forEditRelationship())
                    ->orderColumn('line_no')
                    ->minItems(2)
                    ->columnSpanFull(),
            ]);
    }
}
