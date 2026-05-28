<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\VatSettlements\Pages;

use function Modules\ERP\Helpers\current_company_id;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\VatSettlements\VatSettlementResource;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Services\Accounting\VatSettlementService;
use Override;

final class ListVatSettlements extends ListRecords
{
    #[Override]
    protected static string $resource = VatSettlementResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('compute_settlement')
                ->label('Compute Settlement')
                ->form([
                    Select::make('fiscal_period_id')
                        ->label('Fiscal Period')
                        ->options(
                            FiscalPeriod::query()
                                ->latest('start_date')
                                ->get()
                                ->mapWithKeys(fn (FiscalPeriod $period): array => [
                                    (int) $period->id => 'P' . $period->period_no . ' (' . $period->start_date->format('Y-m-d') . ')',
                                ])
                                ->all(),
                        )
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $company_id = current_company_id();

                    if ($company_id === null) {
                        return;
                    }

                    resolve(VatSettlementService::class)->compute($company_id, (int) $data['fiscal_period_id']);
                    $this->dispatch('$refresh');
                }),
        ];
    }
}
