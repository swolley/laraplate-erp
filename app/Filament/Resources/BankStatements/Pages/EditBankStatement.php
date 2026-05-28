<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankStatements\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Modules\ERP\Filament\Resources\BankStatements\BankStatementResource;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Services\Banking\BankStatementCsvImporter;
use Override;

final class EditBankStatement extends EditRecord
{
    #[Override]
    protected static string $resource = BankStatementResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_csv')
                ->label('Import CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->form([
                    FileUpload::make('csv_file')
                        ->label('CSV file')
                        ->disk('local')
                        ->directory('erp-bank-statements')
                        ->acceptedFileTypes(['text/csv', 'text/plain'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var BankStatement $statement */
                    $statement = $this->record;
                    $path = Storage::disk('local')->path($data['csv_file']);
                    $count = app(BankStatementCsvImporter::class)->import($statement, $path);
                    $this->record->refresh();

                    Notification::make()
                        ->title("Imported {$count} bank statement lines")
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
