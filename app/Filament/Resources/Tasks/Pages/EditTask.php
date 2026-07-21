<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Tasks\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Filament\Resources\Tasks\TaskResource;
use Modules\ERP\Models\Task;
use Modules\ERP\Services\Calendar\TaskIcsExporter;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EditTask extends EditRecord
{
    #[Override] protected static string $resource = TaskResource::class;
    #[Override] protected function getHeaderActions(): array
    {
        return [
            Action::make('export_ics')->label('Export calendar')->icon(Heroicon::OutlinedArrowDownTray)
                ->action(static function (Task $record): StreamedResponse {
                    $exporter = resolve(TaskIcsExporter::class);
                    $ics = $exporter->export($record);
                    return response()->streamDownload(static function () use ($ics): void { echo $ics; }, $exporter->fileName($record), ['Content-Type' => 'text/calendar; charset=UTF-8']);
                }),
            DeleteAction::make(),
        ];
    }
}
