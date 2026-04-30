<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Warehouses;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use Modules\ERP\Filament\Resources\Warehouses\Pages\EditWarehouse;
use Modules\ERP\Filament\Resources\Warehouses\Pages\ListWarehouses;
use Modules\ERP\Filament\Resources\Warehouses\Schemas\WarehouseForm;
use Modules\ERP\Filament\Resources\Warehouses\Tables\WarehousesTable;
use Modules\ERP\Models\Warehouse;
use Override;
use UnitEnum;

final class WarehouseResource extends Resource
{
    #[Override]
    protected static ?string $model = Warehouse::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 38;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/warehouses';
    }

    public static function form(Schema $schema): Schema
    {
        return WarehouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehousesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }

}
