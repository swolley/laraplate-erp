<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankAccounts;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\BankAccounts\Pages\CreateBankAccount;
use Modules\ERP\Filament\Resources\BankAccounts\Pages\EditBankAccount;
use Modules\ERP\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use Modules\ERP\Filament\Resources\BankAccounts\Schemas\BankAccountForm;
use Modules\ERP\Filament\Resources\BankAccounts\Tables\BankAccountsTable;
use Modules\ERP\Models\BankAccount;
use Override;
use UnitEnum;

final class BankAccountResource extends Resource
{
    #[Override]
    protected static ?string $model = BankAccount::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 62;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/bank-accounts';
    }

    public static function form(Schema $schema): Schema
    {
        return BankAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankAccounts::route('/'),
            'create' => CreateBankAccount::route('/create'),
            'edit' => EditBankAccount::route('/{record}/edit'),
        ];
    }
}
