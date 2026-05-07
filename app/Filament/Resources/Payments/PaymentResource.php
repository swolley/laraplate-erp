<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Payments;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\Payments\Pages\CreatePayment;
use Modules\ERP\Filament\Resources\Payments\Pages\EditPayment;
use Modules\ERP\Filament\Resources\Payments\Pages\ListPayments;
use Modules\ERP\Filament\Resources\Payments\Schemas\PaymentForm;
use Modules\ERP\Filament\Resources\Payments\Tables\PaymentsTable;
use Modules\ERP\Models\Payment;
use Override;
use UnitEnum;

final class PaymentResource extends Resource
{
    #[Override]
    protected static ?string $model = Payment::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 61;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/payments';
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('party'))
            ->defaultSort('payment_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }
}
