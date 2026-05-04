<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PurchaseOrders;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use Modules\ERP\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use Modules\ERP\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use Modules\ERP\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use Modules\ERP\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use Modules\ERP\Models\PurchaseOrder;
use Override;
use UnitEnum;

final class PurchaseOrderResource extends Resource
{
    #[Override]
    protected static ?string $model = PurchaseOrder::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 42;

    #[Override]
    protected static ?string $recordTitleAttribute = 'reference';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/purchase-orders';
    }

    /**
     * @return Builder<\Modules\ERP\Models\PurchaseOrder>
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('lines')
            ->withSum('lines', 'qty_ordered')
            ->withSum('lines', 'qty_received');
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
