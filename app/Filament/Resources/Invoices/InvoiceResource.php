<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Invoices;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\Invoices\Pages\CreateInvoice;
use Modules\ERP\Filament\Resources\Invoices\Pages\EditInvoice;
use Modules\ERP\Filament\Resources\Invoices\Pages\ListInvoices;
use Modules\ERP\Filament\Resources\Invoices\Schemas\InvoiceForm;
use Modules\ERP\Filament\Resources\Invoices\Tables\InvoicesTable;
use Modules\ERP\Models\Invoice;
use Override;
use UnitEnum;

final class InvoiceResource extends Resource
{
    #[Override]
    protected static ?string $model = Invoice::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 41;

    #[Override]
    protected static ?string $recordTitleAttribute = 'id';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/invoices';
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }

}
