<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Leads;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\Leads\Pages\CreateLead;
use Modules\ERP\Filament\Resources\Leads\Pages\EditLead;
use Modules\ERP\Filament\Resources\Leads\Pages\ListLeads;
use Modules\ERP\Filament\Resources\Leads\Schemas\LeadForm;
use Modules\ERP\Filament\Resources\Leads\Tables\LeadsTable;
use Modules\ERP\Models\Lead;
use Override;
use UnitEnum;

final class LeadResource extends Resource
{
    #[Override]
    protected static ?string $model = Lead::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'title';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 34;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/leads';
    }

    public static function form(Schema $schema): Schema
    {
        return LeadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'customer', 'contact']))
            ->defaultSort('id', direction: 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'create' => CreateLead::route('/create'),
            'edit' => EditLead::route('/{record}/edit'),
        ];
    }
}
