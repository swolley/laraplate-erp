<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Tasks;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\Tasks\Pages\CreateTask;
use Modules\ERP\Filament\Resources\Tasks\Pages\EditTask;
use Modules\ERP\Filament\Resources\Tasks\Pages\ListTasks;
use Modules\ERP\Filament\Resources\Tasks\Schemas\TaskForm;
use Modules\ERP\Filament\Resources\Tasks\Tables\TasksTable;
use Modules\ERP\Models\Task;
use Override;
use UnitEnum;

final class TaskResource extends Resource
{
    #[Override] protected static ?string $model = Task::class;
    #[Override] protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    #[Override] protected static string|UnitEnum|null $navigationGroup = 'ERP';
    #[Override] protected static ?int $navigationSort = 45;
    public static function getSlug(?Panel $panel = null): string { return 'business/tasks'; }
    public static function form(Schema $schema): Schema { return TaskForm::configure($schema); }
    public static function table(Table $table): Table { return TasksTable::configure($table); }
    public static function getPages(): array { return ['index' => ListTasks::route('/'), 'create' => CreateTask::route('/create'), 'edit' => EditTask::route('/{record}/edit')]; }
}
