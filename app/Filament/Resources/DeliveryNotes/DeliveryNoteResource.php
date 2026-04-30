<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DeliveryNotes;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\DeliveryNotes\Pages\CreateDeliveryNote;
use Modules\ERP\Filament\Resources\DeliveryNotes\Pages\EditDeliveryNote;
use Modules\ERP\Filament\Resources\DeliveryNotes\Pages\ListDeliveryNotes;
use Modules\ERP\Filament\Resources\DeliveryNotes\Schemas\DeliveryNoteForm;
use Modules\ERP\Filament\Resources\DeliveryNotes\Tables\DeliveryNotesTable;
use Modules\ERP\Models\DeliveryNote;
use Override;
use UnitEnum;

final class DeliveryNoteResource extends Resource
{
    #[Override]
    protected static ?string $model = DeliveryNote::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 40;

    #[Override]
    protected static ?string $recordTitleAttribute = 'reference';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/delivery-notes';
    }

    public static function form(Schema $schema): Schema
    {
        return DeliveryNoteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveryNotesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveryNotes::route('/'),
            'create' => CreateDeliveryNote::route('/create'),
            'edit' => EditDeliveryNote::route('/{record}/edit'),
        ];
    }

}
