<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PartnerPools;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\PartnerPools\Pages\CreatePartnerPool;
use Modules\ERP\Filament\Resources\PartnerPools\Pages\EditPartnerPool;
use Modules\ERP\Filament\Resources\PartnerPools\Pages\ListPartnerPools;
use Modules\ERP\Filament\Resources\PartnerPools\Schemas\PartnerPoolForm;
use Modules\ERP\Filament\Resources\PartnerPools\Tables\PartnerPoolsTable;
use Modules\ERP\Models\PartnerPool;
use Override;
use UnitEnum;

final class PartnerPoolResource extends Resource
{
    #[Override]
    protected static ?string $model = PartnerPool::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 63;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/partner-pools';
    }

    public static function form(Schema $schema): Schema
    {
        return PartnerPoolForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnerPoolsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerPools::route('/'),
            'create' => CreatePartnerPool::route('/create'),
            'edit' => EditPartnerPool::route('/{record}/edit'),
        ];
    }
}
