<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\VatSettlements\Pages;

use function Modules\ERP\Helpers\current_company_id;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\VatSettlements\VatSettlementResource;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Services\Accounting\VatSettlementService;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ErpConnectionContext;
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
                        ->options(static function (): array {
                            $company = self::currentCompany();

                            if (! $company instanceof Company) {
                                return [];
                            }

                            return ConnectionScopedModels::for($company)
                                ->query(FiscalPeriod::class)
                                ->whereHas('fiscal_year', static fn ($query) => $query->where('company_id', $company->getKey()))
                                ->latest('start_date')
                                ->get()
                                ->mapWithKeys(fn (FiscalPeriod $period): array => [
                                    (int) $period->id => 'P' . $period->period_no . ' (' . $period->start_date->format('Y-m-d') . ')',
                                ])
                                ->all();
                        })
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $company = self::currentCompany();

                    if (! $company instanceof Company) {
                        return;
                    }

                    $period = ConnectionScopedModels::for($company)
                        ->query(FiscalPeriod::class)
                        ->whereKey((int) $data['fiscal_period_id'])
                        ->whereHas('fiscal_year', static fn ($query) => $query->where('company_id', $company->getKey()))
                        ->firstOrFail();

                    resolve(VatSettlementService::class)->compute($company, $period);
                    $this->dispatch('$refresh');
                }),
        ];
    }

    private static function currentCompany(): ?Company
    {
        $company_id = current_company_id();

        if ($company_id === null) {
            return null;
        }

        return app(ErpConnectionContext::class)
            ->model(Company::class)
            ->newQuery()
            ->find($company_id);
    }
}
