<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Pricing;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Support\ConnectionScopedModels;

final readonly class InvoiceLinePricingService
{
    public function __construct(
        private PriceResolverService $priceResolver,
    ) {}

    /**
     * @return array{description: string, quantity: string, unit_price: string|null}
     */
    public function defaultsFromSalesOrderLine(
        Company $company,
        int $sales_order_line_id,
        ?int $party_id = null,
        string $currency = 'EUR',
    ): array {
        $models = ConnectionScopedModels::for($company);
        $company_id = (int) $company->getKey();

        /** @var SalesOrderLine|null $line */
        $line = $models->query(SalesOrderLine::class)
            ->with(['sales_order', 'item'])
            ->find($sales_order_line_id);

        if ($line === null) {
            throw ValidationException::withMessages([
                'sales_order_line_id' => ['The sales order line does not belong to the selected company.'],
            ]);
        }

        $sales_order = $line->sales_order;

        if ($sales_order === null || $company_id !== $sales_order->company_id) {
            throw ValidationException::withMessages([
                'sales_order_line_id' => ['The sales order line does not belong to the selected company.'],
            ]);
        }

        $unit_price = $this->resolveUnitPrice(
            company: $company,
            line: $line,
            party_id: $party_id ?? $sales_order->party_id,
            currency: $currency,
        );

        return [
            'description' => $line->name,
            'quantity' => number_format(max(0.0, (float) $line->qty_ordered - (float) $line->qty_invoiced), 4, '.', ''),
            'unit_price' => $unit_price,
        ];
    }

    private function resolveUnitPrice(
        Company $company,
        SalesOrderLine $line,
        ?int $party_id,
        string $currency,
    ): ?string {
        if ($line->item_id === null) {
            return $line->unit_price === null ? null : number_format((float) $line->unit_price, 4, '.', '');
        }

        try {
            return $this->priceResolver->resolve(
                company: $company,
                item: $line->item,
                party_id: $party_id,
                currency: $currency,
            )->resolvedUnitPrice;
        } catch (ValidationException) {
            return $line->unit_price === null ? null : number_format((float) $line->unit_price, 4, '.', '');
        }
    }
}
