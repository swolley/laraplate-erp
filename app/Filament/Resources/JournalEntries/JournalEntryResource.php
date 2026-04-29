<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\ERP\Filament\Resources\JournalEntries\Pages\CreateJournalEntry;
use Modules\ERP\Filament\Resources\JournalEntries\Pages\EditJournalEntry;
use Modules\ERP\Filament\Resources\JournalEntries\Pages\ListJournalEntries;
use Modules\ERP\Filament\Resources\JournalEntries\Pages\ViewJournalEntry;
use Modules\ERP\Filament\Resources\JournalEntries\Schemas\JournalEntryInfolist;
use Modules\ERP\Filament\Resources\JournalEntries\Tables\JournalEntriesTable;
use Modules\ERP\Models\JournalEntry;
use Override;
use UnitEnum;

final class JournalEntryResource extends Resource
{
    #[Override]
    protected static ?string $model = JournalEntry::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'id';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 40;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/journal-entries';
    }

    #[Override]
    public static function canEdit(Model $record): bool
    {
        if (! parent::canEdit($record)) {
            return false;
        }

        return $record instanceof JournalEntry && $record->posted_at === null;
    }

    #[Override]
    public static function canDelete(Model $record): bool
    {
        if (! parent::canDelete($record)) {
            return false;
        }

        return $record instanceof JournalEntry && $record->posted_at === null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return JournalEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JournalEntriesTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('company'))
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJournalEntries::route('/'),
            'create' => CreateJournalEntry::route('/create'),
            'view' => ViewJournalEntry::route('/{record}'),
            'edit' => EditJournalEntry::route('/{record}/edit'),
        ];
    }
}
