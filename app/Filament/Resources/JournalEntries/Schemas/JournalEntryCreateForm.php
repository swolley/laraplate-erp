<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\JournalEntries\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;

final class JournalEntryCreateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...JournalEntryHeaderFields::components(company_locked: false),
                Repeater::make('line_items')
                    ->schema(JournalEntryLineSchema::forCreateRepeater())
                    ->defaultItems(2)
                    ->minItems(2)
                    ->addActionLabel('Add line')
                    ->columnSpanFull(),
            ]);
    }
}
