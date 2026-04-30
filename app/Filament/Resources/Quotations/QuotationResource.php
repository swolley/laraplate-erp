<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Quotations;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\Quotations\Pages\CreateQuotation;
use Modules\ERP\Filament\Resources\Quotations\Pages\EditQuotation;
use Modules\ERP\Filament\Resources\Quotations\Pages\ListQuotations;
use Modules\ERP\Filament\Resources\Quotations\Schemas\QuotationForm;
use Modules\ERP\Filament\Resources\Quotations\Tables\QuotationsTable;
use Modules\ERP\Models\Quotation;
use Override;
use UnitEnum;

final class QuotationResource extends Resource
{
    #[Override]
    protected static ?string $model = Quotation::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'id';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 32;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/quotations';
    }

    public static function form(Schema $schema): Schema
    {
        return QuotationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotationsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'customer', 'opportunity']))
            ->defaultSort('id', direction: 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotations::route('/'),
            'create' => CreateQuotation::route('/create'),
            'edit' => EditQuotation::route('/{record}/edit'),
        ];
    }
}
