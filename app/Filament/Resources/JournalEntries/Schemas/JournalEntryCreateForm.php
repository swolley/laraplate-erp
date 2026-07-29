<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;

final class JournalEntryCreateForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

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
