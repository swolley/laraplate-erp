<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\Accounts;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Business\Filament\Resources\Accounts\Pages\CreateAccount;
use Modules\Business\Filament\Resources\Accounts\Pages\EditAccount;
use Modules\Business\Filament\Resources\Accounts\Pages\ListAccounts;
use Modules\Business\Filament\Resources\Accounts\Schemas\AccountForm;
use Modules\Business\Filament\Resources\Accounts\Tables\AccountsTable;
use Modules\Business\Models\Account;
use Override;
use UnitEnum;

final class AccountResource extends Resource
{
    #[Override]
    protected static ?string $model = Account::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'code';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Business';

    #[Override]
    protected static ?int $navigationSort = 30;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/accounts';
    }

    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'parent']))
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }
}
