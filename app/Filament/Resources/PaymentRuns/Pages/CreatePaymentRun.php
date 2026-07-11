<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRuns\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\ERP\Filament\Resources\PaymentRuns\PaymentRunResource;
use Modules\ERP\Services\Payments\PaymentRunBuilderService;
use Override;

final class CreatePaymentRun extends CreateRecord
{
    #[Override]
    protected static string $resource = PaymentRunResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        return app(PaymentRunBuilderService::class)->build(
            (int) $data['company_id'],
            (int) $data['bank_account_id'],
            array_map('intval', $data['payment_schedule_line_ids'] ?? []),
            (string) $data['execution_date'],
        );
    }
}
