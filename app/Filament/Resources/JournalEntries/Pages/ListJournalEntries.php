<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\JournalEntries\JournalEntryResource;
use Override;

final class ListJournalEntries extends ListRecords
{
    #[Override]
    protected static string $resource = JournalEntryResource::class;
}
