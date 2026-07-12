<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentTerms;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\PaymentTerms\Pages\CreatePaymentTerm;
use Modules\ERP\Filament\Resources\PaymentTerms\Pages\EditPaymentTerm;
use Modules\ERP\Filament\Resources\PaymentTerms\Pages\ListPaymentTerms;
use Modules\ERP\Filament\Resources\PaymentTerms\Schemas\PaymentTermForm;
use Modules\ERP\Filament\Resources\PaymentTerms\Tables\PaymentTermsTable;
use Modules\ERP\Models\PaymentTerm;
use Override;
use UnitEnum;

final class PaymentTermResource extends Resource
{
    #[Override]
    protected static ?string $model = PaymentTerm::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 60;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/payment-terms';
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentTermForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentTermsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('company'))
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentTerms::route('/'),
            'create' => CreatePaymentTerm::route('/create'),
            'edit' => EditPaymentTerm::route('/{record}/edit'),
        ];
    }
}
