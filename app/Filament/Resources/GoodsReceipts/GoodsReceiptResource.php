<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\GoodsReceipts;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use Modules\ERP\Filament\Resources\GoodsReceipts\Pages\EditGoodsReceipt;
use Modules\ERP\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use Modules\ERP\Filament\Resources\GoodsReceipts\Schemas\GoodsReceiptForm;
use Modules\ERP\Filament\Resources\GoodsReceipts\Tables\GoodsReceiptsTable;
use Modules\ERP\Models\GoodsReceipt;
use Override;
use UnitEnum;

final class GoodsReceiptResource extends Resource
{
    #[Override]
    protected static ?string $model = GoodsReceipt::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 43;

    #[Override]
    protected static ?string $recordTitleAttribute = 'reference';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/goods-receipts';
    }

    public static function form(Schema $schema): Schema
    {
        return GoodsReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GoodsReceiptsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoodsReceipts::route('/'),
            'create' => CreateGoodsReceipt::route('/create'),
            'edit' => EditGoodsReceipt::route('/{record}/edit'),
        ];
    }

}
