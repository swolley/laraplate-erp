<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\VatRegister;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\VatRegister\Pages\ListVatRegisterEntries;
use Modules\ERP\Filament\Resources\VatRegister\Tables\VatRegisterTable;
use Modules\ERP\Models\VatRegisterEntry;
use Override;
use UnitEnum;

final class VatRegisterResource extends Resource
{
    #[Override]
    protected static ?string $model = VatRegisterEntry::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 70;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/vat-register';
    }

    public static function table(Table $table): Table
    {
        return VatRegisterTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['invoice', 'tax_code', 'fiscal_year']))
            ->defaultSort('protocol_number', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVatRegisterEntries::route('/'),
        ];
    }
}
