<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Opportunities;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\Opportunities\Pages\CreateOpportunity;
use Modules\ERP\Filament\Resources\Opportunities\Pages\EditOpportunity;
use Modules\ERP\Filament\Resources\Opportunities\Pages\ListOpportunities;
use Modules\ERP\Filament\Resources\Opportunities\Schemas\OpportunityForm;
use Modules\ERP\Filament\Resources\Opportunities\Tables\OpportunitiesTable;
use Modules\ERP\Models\Opportunity;
use Override;
use UnitEnum;

final class OpportunityResource extends Resource
{
    #[Override]
    protected static ?string $model = Opportunity::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 35;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/opportunities';
    }

    public static function form(Schema $schema): Schema
    {
        return OpportunityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpportunitiesTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'customer', 'lead', 'stage']))
            ->defaultSort('id', direction: 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpportunities::route('/'),
            'create' => CreateOpportunity::route('/create'),
            'edit' => EditOpportunity::route('/{record}/edit'),
        ];
    }
}
