<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Contacts;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\Contacts\Pages\CreateContact;
use Modules\ERP\Filament\Resources\Contacts\Pages\EditContact;
use Modules\ERP\Filament\Resources\Contacts\Pages\ListContacts;
use Modules\ERP\Filament\Resources\Contacts\Schemas\ContactForm;
use Modules\ERP\Filament\Resources\Contacts\Tables\ContactsTable;
use Modules\ERP\Models\Contact;
use Override;
use UnitEnum;

final class ContactResource extends Resource
{
    #[Override]
    protected static ?string $model = Contact::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 31;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/contacts';
    }

    public static function form(Schema $schema): Schema
    {
        return ContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactsTable::configure($table)
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
            'index' => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'edit' => EditContact::route('/{record}/edit'),
        ];
    }
}
