<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Projects;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Filament\Resources\Projects\Pages\CreateProject;
use Modules\ERP\Filament\Resources\Projects\Pages\EditProject;
use Modules\ERP\Filament\Resources\Projects\Pages\ListProjects;
use Modules\ERP\Filament\Resources\Projects\Schemas\ProjectForm;
use Modules\ERP\Filament\Resources\Projects\Tables\ProjectsTable;
use Modules\ERP\Models\Project;
use Override;
use UnitEnum;

final class ProjectResource extends Resource
{
    #[Override]
    protected static ?string $model = Project::class;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 33;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'business/projects';
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'customer']))
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
