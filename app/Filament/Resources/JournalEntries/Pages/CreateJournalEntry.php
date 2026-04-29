<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Modules\ERP\Filament\Resources\JournalEntries\JournalEntryResource;
use Modules\ERP\Filament\Resources\JournalEntries\Schemas\JournalEntryCreateForm;
use Modules\ERP\Models\JournalEntry;
use Override;

final class CreateJournalEntry extends CreateRecord
{
    #[Override]
    protected static string $resource = JournalEntryResource::class;

    public function form(Schema $schema): Schema
    {
        return JournalEntryCreateForm::configure($schema);
    }

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $line_items = $data['line_items'] ?? [];
        unset($data['line_items']);

        /** @var JournalEntry $record */
        $record = JournalEntry::query()->create($data);

        foreach (array_values($line_items) as $index => $line) {
            $record->lines()->create([
                ...$line,
                'line_no' => $index + 1,
            ]);
        }

        return $record;
    }
}
