<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Customers;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\Customers\Pages\CreateCustomer;
use Modules\ERP\Filament\Resources\Customers\Pages\EditCustomer;
use Modules\ERP\Filament\Resources\Customers\Pages\ListCustomers;
use Modules\ERP\Filament\Resources\Customers\Schemas\CustomerForm;
use Modules\ERP\Filament\Resources\Customers\Tables\CustomersTable;
use Modules\ERP\Models\Customer;
use Override;
use UnitEnum;

final class CustomerResource extends Resource
{
    #[Override]
    protected static ?string $model = Customer::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 30;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/customers';
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table)
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
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
