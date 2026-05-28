<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\ReturnOrders;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\ReturnOrders\Pages\CreateReturnOrder;
use Modules\ERP\Filament\Resources\ReturnOrders\Pages\EditReturnOrder;
use Modules\ERP\Filament\Resources\ReturnOrders\Pages\ListReturnOrders;
use Modules\ERP\Filament\Resources\ReturnOrders\Schemas\ReturnOrderForm;
use Modules\ERP\Filament\Resources\ReturnOrders\Tables\ReturnOrdersTable;
use Modules\ERP\Models\ReturnOrder;
use Override;
use UnitEnum;

final class ReturnOrderResource extends Resource
{
    #[Override]
    protected static ?string $model = ReturnOrder::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 64;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/return-orders';
    }

    public static function form(Schema $schema): Schema
    {
        return ReturnOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReturnOrdersTable::configure($table)->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturnOrders::route('/'),
            'create' => CreateReturnOrder::route('/create'),
            'edit' => EditReturnOrder::route('/{record}/edit'),
        ];
    }
}
