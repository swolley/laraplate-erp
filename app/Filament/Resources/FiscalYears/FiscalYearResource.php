<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalYears;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\FiscalYears\Pages\CreateFiscalYear;
use Modules\ERP\Filament\Resources\FiscalYears\Pages\EditFiscalYear;
use Modules\ERP\Filament\Resources\FiscalYears\Pages\ListFiscalYears;
use Modules\ERP\Filament\Resources\FiscalYears\Schemas\FiscalYearForm;
use Modules\ERP\Filament\Resources\FiscalYears\Tables\FiscalYearsTable;
use Modules\ERP\Models\FiscalYear;
use Override;
use UnitEnum;

final class FiscalYearResource extends Resource
{
    #[Override]
    protected static ?string $model = FiscalYear::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'year';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 50;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/fiscal-years';
    }

    public static function form(Schema $schema): Schema
    {
        return FiscalYearForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FiscalYearsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('company'))
            ->defaultSort('year', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFiscalYears::route('/'),
            'create' => CreateFiscalYear::route('/create'),
            'edit' => EditFiscalYear::route('/{record}/edit'),
        ];
    }
}
