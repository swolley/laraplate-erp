<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\ReturnOrders\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Filament\Resources\ReturnOrders\ReturnOrderResource;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Services\Returns\ReturnOrderService;
use Override;

final class EditReturnOrder extends EditRecord
{
    #[Override]
    protected static string $resource = ReturnOrderResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof ReturnOrder
                    && $this->record->status === ReturnStatus::Draft)
                ->action(function (): void {
                    app(ReturnOrderService::class)->approve($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Return approved')
                        ->success()
                        ->send();
                }),
            Action::make('complete')
                ->label('Complete')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof ReturnOrder
                    && $this->record->status === ReturnStatus::Approved)
                ->action(function (): void {
                    app(ReturnOrderService::class)->complete($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Return completed')
                        ->success()
                        ->send();
                }),
            Action::make('cancel')
                ->label('Cancel')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof ReturnOrder
                    && in_array($this->record->status, [ReturnStatus::Draft, ReturnStatus::Approved], true))
                ->action(function (): void {
                    app(ReturnOrderService::class)->cancel($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Return cancelled')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
