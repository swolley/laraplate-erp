<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SupplierReturns;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\SupplierReturns\Pages\CreateSupplierReturn;
use Modules\ERP\Filament\Resources\SupplierReturns\Pages\EditSupplierReturn;
use Modules\ERP\Filament\Resources\SupplierReturns\Pages\ListSupplierReturns;
use Modules\ERP\Filament\Resources\SupplierReturns\Schemas\SupplierReturnForm;
use Modules\ERP\Filament\Resources\SupplierReturns\Tables\SupplierReturnsTable;
use Modules\ERP\Models\SupplierReturn;
use Override;
use UnitEnum;

final class SupplierReturnResource extends Resource
{
    #[Override]
    protected static ?string $model = SupplierReturn::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightCircle;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 65;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/supplier-returns';
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierReturnsTable::configure($table)->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierReturns::route('/'),
            'create' => CreateSupplierReturn::route('/create'),
            'edit' => EditSupplierReturn::route('/{record}/edit'),
        ];
    }
}
