<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\VatSettlements;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\VatSettlements\Pages\ListVatSettlements;
use Modules\ERP\Filament\Resources\VatSettlements\Tables\VatSettlementsTable;
use Modules\ERP\Models\VatSettlement;
use Override;
use UnitEnum;

final class VatSettlementResource extends Resource
{
    #[Override]
    protected static ?string $model = VatSettlement::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPercentBadge;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 71;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/vat-settlements';
    }

    public static function table(Table $table): Table
    {
        return VatSettlementsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('fiscal_period'))
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVatSettlements::route('/'),
        ];
    }
}
