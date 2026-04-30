<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Items;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\Items\Pages\CreateItem;
use Modules\ERP\Filament\Resources\Items\Pages\EditItem;
use Modules\ERP\Filament\Resources\Items\Pages\ListItems;
use Modules\ERP\Filament\Resources\Items\Schemas\ItemForm;
use Modules\ERP\Filament\Resources\Items\Tables\ItemsTable;
use Modules\ERP\Models\Item;
use Override;
use UnitEnum;

final class ItemResource extends Resource
{
    #[Override]
    protected static ?string $model = Item::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 37;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/items';
    }

    public static function form(Schema $schema): Schema
    {
        return ItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }

}
