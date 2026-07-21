<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRequests\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\PaymentRequests\PaymentRequestResource;
use Override;

final class ListPaymentRequests extends ListRecords
{
    #[Override] protected static string $resource = PaymentRequestResource::class;
    #[Override] protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
