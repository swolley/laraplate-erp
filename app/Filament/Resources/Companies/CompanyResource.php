<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Companies;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\Companies\Pages\CreateCompany;
use Modules\ERP\Filament\Resources\Companies\Pages\EditCompany;
use Modules\ERP\Filament\Resources\Companies\Pages\ListCompanies;
use Modules\ERP\Filament\Resources\Companies\Schemas\CompanyForm;
use Modules\ERP\Filament\Resources\Companies\Tables\CompaniesTable;
use Modules\ERP\Models\Company;
use Override;
use UnitEnum;

final class CompanyResource extends Resource
{
    #[Override]
    protected static ?string $model = Company::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 10;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/companies';
    }

    public static function form(Schema $schema): Schema
    {
        return CompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompaniesTable::configure($table)
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
