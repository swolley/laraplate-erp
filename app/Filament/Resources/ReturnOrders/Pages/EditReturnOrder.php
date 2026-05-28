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
use Modules\ERP\Services\Returns\CustomerReturnReceiptService;
use Override;

final class EditReturnOrder extends EditRecord
{
    #[Override]
    protected static string $resource = ReturnOrderResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label('Complete')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof ReturnOrder
                    && $this->record->status !== ReturnStatus::Processed)
                ->action(function (): void {
                    app(CustomerReturnReceiptService::class)->receive($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Return completed')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
