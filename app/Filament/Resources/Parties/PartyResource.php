<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Parties;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\Parties\Pages\CreateParty;
use Modules\ERP\Filament\Resources\Parties\Pages\EditParty;
use Modules\ERP\Filament\Resources\Parties\Pages\ListParties;
use Modules\ERP\Filament\Resources\Parties\RelationManagers\PriceRulesRelationManager;
use Modules\ERP\Filament\Resources\Parties\Schemas\PartyForm;
use Modules\ERP\Filament\Resources\Parties\Tables\PartiesTable;
use Modules\ERP\Models\Party;
use Override;
use UnitEnum;

final class PartyResource extends Resource
{
    #[Override]
    protected static ?string $model = Party::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 30;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/parties';
    }

    public static function form(Schema $schema): Schema
    {
        return PartyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartiesTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('company'))
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            PriceRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParties::route('/'),
            'create' => CreateParty::route('/create'),
            'edit' => EditParty::route('/{record}/edit'),
        ];
    }
}
