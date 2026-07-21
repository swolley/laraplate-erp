<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRequests;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\PaymentRequests\Pages\CreatePaymentRequest;
use Modules\ERP\Filament\Resources\PaymentRequests\Pages\EditPaymentRequest;
use Modules\ERP\Filament\Resources\PaymentRequests\Pages\ListPaymentRequests;
use Modules\ERP\Filament\Resources\PaymentRequests\Schemas\PaymentRequestForm;
use Modules\ERP\Filament\Resources\PaymentRequests\Tables\PaymentRequestsTable;
use Modules\ERP\Models\PaymentRequest;
use Override;
use UnitEnum;

final class PaymentRequestResource extends Resource
{
    #[Override] protected static ?string $model = PaymentRequest::class;
    #[Override] protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;
    #[Override] protected static string|UnitEnum|null $navigationGroup = 'ERP';
    #[Override] protected static ?int $navigationSort = 64;

    public static function getSlug(?Panel $panel = null): string { return 'business/payment-requests'; }
    public static function form(Schema $schema): Schema { return PaymentRequestForm::configure($schema); }
    public static function table(Table $table): Table { return PaymentRequestsTable::configure($table); }
    public static function getPages(): array
    {
        return ['index' => ListPaymentRequests::route('/'), 'create' => CreatePaymentRequest::route('/create'), 'edit' => EditPaymentRequest::route('/{record}/edit')];
    }
}
