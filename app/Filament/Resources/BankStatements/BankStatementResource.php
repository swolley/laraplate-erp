<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankStatements;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\BankStatements\Pages\CreateBankStatement;
use Modules\ERP\Filament\Resources\BankStatements\Pages\EditBankStatement;
use Modules\ERP\Filament\Resources\BankStatements\Pages\ListBankStatements;
use Modules\ERP\Filament\Resources\BankStatements\Schemas\BankStatementForm;
use Modules\ERP\Filament\Resources\BankStatements\Tables\BankStatementsTable;
use Modules\ERP\Models\BankStatement;
use Override;
use UnitEnum;

final class BankStatementResource extends Resource
{
    #[Override]
    protected static ?string $model = BankStatement::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 63;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/bank-statements';
    }

    public static function form(Schema $schema): Schema
    {
        return BankStatementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankStatementsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('bank_account')->withCount('lines'))
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankStatements::route('/'),
            'create' => CreateBankStatement::route('/create'),
            'edit' => EditBankStatement::route('/{record}/edit'),
        ];
    }
}
