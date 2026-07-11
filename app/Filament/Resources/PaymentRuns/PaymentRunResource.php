<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRuns;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\ERP\Casts\PaymentRunStatus;
use Modules\ERP\Filament\Resources\PaymentRuns\Pages\CreatePaymentRun;
use Modules\ERP\Filament\Resources\PaymentRuns\Pages\EditPaymentRun;
use Modules\ERP\Filament\Resources\PaymentRuns\Pages\ListPaymentRuns;
use Modules\ERP\Filament\Resources\PaymentRuns\Schemas\PaymentRunForm;
use Modules\ERP\Filament\Resources\PaymentRuns\Tables\PaymentRunsTable;
use Modules\ERP\Models\PaymentRun;
use Override;
use UnitEnum;

final class PaymentRunResource extends Resource
{
    #[Override]
    protected static ?string $model = PaymentRun::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'id';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 63;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/payment-runs';
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentRunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentRunsTable::configure($table)
            ->defaultSort('execution_date', 'desc');
    }

    public static function canEdit(Model $record): bool
    {
        if ($record instanceof PaymentRun && $record->status === PaymentRunStatus::Exported) {
            return false;
        }

        return parent::canEdit($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentRuns::route('/'),
            'create' => CreatePaymentRun::route('/create'),
            'edit' => EditPaymentRun::route('/{record}/edit'),
        ];
    }
}
