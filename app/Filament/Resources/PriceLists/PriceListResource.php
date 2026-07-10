<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PriceLists;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\PriceLists\Pages\CreatePriceList;
use Modules\ERP\Filament\Resources\PriceLists\Pages\EditPriceList;
use Modules\ERP\Filament\Resources\PriceLists\Pages\ListPriceLists;
use Modules\ERP\Filament\Resources\PriceLists\Schemas\PriceListForm;
use Modules\ERP\Filament\Resources\PriceLists\Tables\PriceListsTable;
use Modules\ERP\Models\PriceList;
use Override;
use UnitEnum;

final class PriceListResource extends Resource
{
    #[Override]
    protected static ?string $model = PriceList::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 35;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/price-lists';
    }

    public static function form(Schema $schema): Schema
    {
        return PriceListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceListsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('company')->withCount('price_list_items'))
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}
