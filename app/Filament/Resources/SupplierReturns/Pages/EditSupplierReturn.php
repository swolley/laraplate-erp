<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SupplierReturns\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Filament\Resources\SupplierReturns\SupplierReturnResource;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Services\Returns\SupplierReturnShipmentService;
use Override;

final class EditSupplierReturn extends EditRecord
{
    #[Override]
    protected static string $resource = SupplierReturnResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label('Complete')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof SupplierReturn
                    && $this->record->status !== ReturnStatus::Processed)
                ->action(function (): void {
                    app(SupplierReturnShipmentService::class)->ship($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Supplier return completed')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
