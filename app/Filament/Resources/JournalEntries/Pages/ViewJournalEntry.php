<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\ERP\Filament\Resources\JournalEntries\Actions\JournalEntryActions;
use Modules\ERP\Filament\Resources\JournalEntries\JournalEntryResource;
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

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            JournalEntryActions::reverse(),
        ];
    }
}
