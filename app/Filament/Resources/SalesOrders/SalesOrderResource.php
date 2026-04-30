<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SalesOrders;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use Modules\ERP\Filament\Resources\SalesOrders\Pages\EditSalesOrder;
use Modules\ERP\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use Modules\ERP\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use Modules\ERP\Filament\Resources\SalesOrders\Tables\SalesOrdersTable;
use Modules\ERP\Models\SalesOrder;
use Override;
use UnitEnum;

final class SalesOrderResource extends Resource
{
    #[Override]
    protected static ?string $model = SalesOrder::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'reference';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 36;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/sales-orders';
    }

    public static function form(Schema $schema): Schema
    {
        return SalesOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesOrdersTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'customer', 'quotation', 'project']))
            ->defaultSort('id', direction: 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesOrders::route('/'),
            'create' => CreateSalesOrder::route('/create'),
            'edit' => EditSalesOrder::route('/{record}/edit'),
        ];
    }
}
