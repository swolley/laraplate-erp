<?php
declare(strict_types=1);
namespace Modules\ERP\Filament\Resources\Tasks\Pages;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Tasks\TaskResource;
use Override;
final class ListTasks extends ListRecords { #[Override] protected static string $resource = TaskResource::class; #[Override] protected function getHeaderActions(): array { return [CreateAction::make()]; } }
