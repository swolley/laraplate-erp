<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SupplierReturns\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Filament\Resources\Invoices\InvoiceResource;
use Modules\ERP\Filament\Resources\SupplierReturns\SupplierReturnResource;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Services\Returns\SupplierReturnService;
use Override;

final class EditSupplierReturn extends EditRecord
{
    #[Override]
    protected static string $resource = SupplierReturnResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof SupplierReturn
                    && $this->record->status === ReturnStatus::Draft)
                ->action(function (): void {
                    app(SupplierReturnService::class)->approve($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Supplier return approved')
                        ->success()
                        ->send();
                }),
            Action::make('complete')
                ->label('Complete')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof SupplierReturn
                    && $this->record->status === ReturnStatus::Approved)
                ->action(function (): void {
                    app(SupplierReturnService::class)->complete($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Supplier return completed')
                        ->success()
                        ->send();
                }),
            Action::make('cancel')
                ->label('Cancel')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof SupplierReturn
                    && in_array($this->record->status, [ReturnStatus::Draft, ReturnStatus::Approved], true))
                ->action(function (): void {
                    app(SupplierReturnService::class)->cancel($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Supplier return cancelled')
                        ->success()
                        ->send();
                }),
            Action::make('reverse_processed')
                ->label('Reverse')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof SupplierReturn
                    && $this->record->status === ReturnStatus::Processed
                    && $this->record->debit_note_invoice_id === null)
                ->action(function (): void {
                    app(SupplierReturnService::class)->reverseProcessed($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Supplier return reversed')
                        ->success()
                        ->send();
                }),
            Action::make('create_debit_note')
                ->label('Create Debit Note')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof SupplierReturn
                    && $this->record->status === ReturnStatus::Processed
                    && $this->record->purchase_order_id !== null
                    && $this->record->debit_note_invoice_id === null)
                ->action(function (): void {
                    $debit_note = app(SupplierReturnService::class)->createDebitNote($this->record);
                    $this->redirect(InvoiceResource::getUrl('edit', ['record' => $debit_note]));
                }),
            DeleteAction::make(),
        ];
    }
}
