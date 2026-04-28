<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\DocumentSequences;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Business\Filament\Resources\DocumentSequences\Pages\CreateDocumentSequence;
use Modules\Business\Filament\Resources\DocumentSequences\Pages\EditDocumentSequence;
use Modules\Business\Filament\Resources\DocumentSequences\Pages\ListDocumentSequences;
use Modules\Business\Filament\Resources\DocumentSequences\Schemas\DocumentSequenceForm;
use Modules\Business\Filament\Resources\DocumentSequences\Tables\DocumentSequencesTable;
use Modules\Business\Models\DocumentSequence;
use Override;
use UnitEnum;

final class DocumentSequenceResource extends Resource
{
    #[Override]
    protected static ?string $model = DocumentSequence::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Business';

    #[Override]
    protected static ?int $navigationSort = 70;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/document-sequences';
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentSequenceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentSequencesTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('company'))
            ->defaultSort('document_type');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentSequences::route('/'),
            'create' => CreateDocumentSequence::route('/create'),
            'edit' => EditDocumentSequence::route('/{record}/edit'),
        ];
    }
}
