<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\JournalEntries\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Modules\Business\Filament\Resources\JournalEntries\JournalEntryResource;
use Modules\Business\Filament\Resources\JournalEntries\Schemas\JournalEntryEditForm;
use Override;

final class EditJournalEntry extends EditRecord
{
    #[Override]
    protected static string $resource = JournalEntryResource::class;

    public function form(Schema $schema): Schema
    {
        return JournalEntryEditForm::configure($schema);
    }
}
