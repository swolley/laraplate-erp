<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\TaxCodes;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Business\Filament\Resources\TaxCodes\Pages\CreateTaxCode;
use Modules\Business\Filament\Resources\TaxCodes\Pages\EditTaxCode;
use Modules\Business\Filament\Resources\TaxCodes\Pages\ListTaxCodes;
use Modules\Business\Filament\Resources\TaxCodes\Schemas\TaxCodeForm;
use Modules\Business\Filament\Resources\TaxCodes\Tables\TaxCodesTable;
use Modules\Business\Models\TaxCode;
use Override;
use UnitEnum;

final class TaxCodeResource extends Resource
{
    #[Override]
    protected static ?string $model = TaxCode::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'code';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Business';

    #[Override]
    protected static ?int $navigationSort = 20;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/tax-codes';
    }

    public static function form(Schema $schema): Schema
    {
        return TaxCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxCodesTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('company'))
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxCodes::route('/'),
            'create' => CreateTaxCode::route('/create'),
            'edit' => EditTaxCode::route('/{record}/edit'),
        ];
    }
}
