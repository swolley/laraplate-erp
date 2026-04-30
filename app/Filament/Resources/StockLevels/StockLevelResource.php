<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\StockLevels;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\StockLevels\Pages\CreateStockLevel;
use Modules\ERP\Filament\Resources\StockLevels\Pages\EditStockLevel;
use Modules\ERP\Filament\Resources\StockLevels\Pages\ListStockLevels;
use Modules\ERP\Filament\Resources\StockLevels\Schemas\StockLevelForm;
use Modules\ERP\Filament\Resources\StockLevels\Tables\StockLevelsTable;
use Modules\ERP\Models\StockLevel;
use Override;
use UnitEnum;

final class StockLevelResource extends Resource
{
    #[Override]
    protected static ?string $model = StockLevel::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 39;

    #[Override]
    protected static ?string $recordTitleAttribute = 'id';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/stock-levels';
    }

    public static function form(Schema $schema): Schema
    {
        return StockLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockLevelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockLevels::route('/'),
            'create' => CreateStockLevel::route('/create'),
            'edit' => EditStockLevel::route('/{record}/edit'),
        ];
    }

}
