<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalPeriods;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\FiscalPeriods\Pages\CreateFiscalPeriod;
use Modules\ERP\Filament\Resources\FiscalPeriods\Pages\EditFiscalPeriod;
use Modules\ERP\Filament\Resources\FiscalPeriods\Pages\ListFiscalPeriods;
use Modules\ERP\Filament\Resources\FiscalPeriods\Schemas\FiscalPeriodForm;
use Modules\ERP\Filament\Resources\FiscalPeriods\Tables\FiscalPeriodsTable;
use Modules\ERP\Models\FiscalPeriod;
use Override;
use UnitEnum;

final class FiscalPeriodResource extends Resource
{
    #[Override]
    protected static ?string $model = FiscalPeriod::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'period_no';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 60;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/fiscal-periods';
    }

    public static function form(Schema $schema): Schema
    {
        return FiscalPeriodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FiscalPeriodsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('fiscal_year.company'))
            ->defaultSort('start_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFiscalPeriods::route('/'),
            'create' => CreateFiscalPeriod::route('/create'),
            'edit' => EditFiscalPeriod::route('/{record}/edit'),
        ];
    }
}
