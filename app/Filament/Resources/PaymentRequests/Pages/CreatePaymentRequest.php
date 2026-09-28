<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRequests\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Casts\PaymentRequestStatus;
use Modules\ERP\Filament\Resources\PaymentRequests\PaymentRequestResource;
use Override;

final class CreatePaymentRequest extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = PaymentRequestResource::class;

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = PaymentRequestStatus::Draft->value;
        $data['provider_code'] = 'stub';

        return $data;
    }
}
