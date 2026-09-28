<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\JournalEntries\JournalEntryResource;
use Modules\ERP\Filament\Resources\JournalEntries\Schemas\JournalEntryEditForm;
use Override;

final class EditJournalEntry extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = JournalEntryResource::class;

    public function form(Schema $schema): Schema
    {
        return JournalEntryEditForm::configure($schema);
    }
}
