<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Movements;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\Movements\Pages\CreateMovement;
use Modules\ERP\Filament\Resources\Movements\Pages\ListMovements;
use Modules\ERP\Filament\Resources\Movements\Pages\ViewMovement;
use Modules\ERP\Filament\Resources\Movements\Schemas\MovementForm;
use Modules\ERP\Filament\Resources\Movements\Schemas\MovementInfolist;
use Modules\ERP\Filament\Resources\Movements\Tables\MovementsTable;
use Modules\ERP\Models\Movement;
use Override;
use UnitEnum;

final class MovementResource extends Resource
{
    #[Override]
    protected static ?string $model = Movement::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 62;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/cash-movements';
    }

    public static function form(Schema $schema): Schema
    {
        return MovementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MovementsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'counterparty_account', 'posted_journal_entry']))
            ->defaultSort('occurred_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMovements::route('/'),
            'create' => CreateMovement::route('/create'),
            'view' => ViewMovement::route('/{record}'),
        ];
    }
}
