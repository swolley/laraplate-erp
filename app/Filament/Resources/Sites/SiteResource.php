<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Sites;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\Sites\Pages\CreateSite;
use Modules\ERP\Filament\Resources\Sites\Pages\EditSite;
use Modules\ERP\Filament\Resources\Sites\Pages\ListSites;
use Modules\ERP\Filament\Resources\Sites\Schemas\SiteForm;
use Modules\ERP\Filament\Resources\Sites\Tables\SitesTable;
use Modules\ERP\Models\Site;
use Override;
use UnitEnum;

final class SiteResource extends Resource
{
    #[Override] protected static ?string $model = Site::class;
    #[Override] protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;
    #[Override] protected static string|UnitEnum|null $navigationGroup = 'ERP';
    #[Override] protected static ?int $navigationSort = 37;
    #[Override] protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string { return 'business/sites'; }
    public static function form(Schema $schema): Schema { return SiteForm::configure($schema); }
    public static function table(Table $table): Table { return SitesTable::configure($table); }
    public static function getPages(): array
    {
        return ['index' => ListSites::route('/'), 'create' => CreateSite::route('/create'), 'edit' => EditSite::route('/{record}/edit')];
    }
}
