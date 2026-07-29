<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;

final class JournalEntryEditForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

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
