<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\JournalEntries\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Business\Filament\Resources\JournalEntries\JournalEntryResource;
use Override;

final class ViewJournalEntry extends ViewRecord
{
    #[Override]
    protected static string $resource = JournalEntryResource::class;

    #[Override]
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing([
            'lines.account',
            'company',
            'fiscal_period.fiscal_year',
        ]);
    }
}
