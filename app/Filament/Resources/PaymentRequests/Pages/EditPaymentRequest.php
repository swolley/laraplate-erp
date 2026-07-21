<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRequests\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Casts\PaymentRequestStatus;
use Modules\ERP\Filament\Resources\PaymentRequests\PaymentRequestResource;
use Modules\ERP\Models\PaymentRequest;
use Modules\ERP\Services\Payments\PaymentRequestService;
use Override;

final class EditPaymentRequest extends EditRecord
{
    #[Override] protected static string $resource = PaymentRequestResource::class;
    #[Override] protected function getHeaderActions(): array
    {
        return [
            Action::make('send')->icon(Heroicon::OutlinedPaperAirplane)->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof PaymentRequest && $this->record->status === PaymentRequestStatus::Draft)
                ->action(function (): void {
                    resolve(PaymentRequestService::class)->send($this->record);
                    $this->record->refresh();
                    Notification::make()->title('Payment request sent')->success()->send();
                }),
            DeleteAction::make()->visible(fn (): bool => $this->record->status === PaymentRequestStatus::Draft),
        ];
    }
}
