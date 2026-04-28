<?php

declare(strict_types=1);

namespace Modules\Business\Services\Taxation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Modules\Business\Casts\TaxKind;
use Modules\Business\Exceptions\TaxCodeNotActiveException;
use Modules\Business\Exceptions\TaxKindMismatchException;
use Modules\Business\Models\Company;
use Modules\Business\Models\TaxCode;

/**
 * VAT / withholding math and resolution of active {@see TaxCode} rows at a posting date.
 */
final class TaxLineCalculator
{
    public function resolveActiveAt(Company $company, string $code, DateTimeInterface $on_date): TaxCode
    {
        $row = TaxCode::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $on_date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            throw TaxCodeNotActiveException::forCode($code, (int) $company->id);
        }

        return $row;
    }

    /**
     * @return array{taxable: string, tax: string, gross: string}
     */
    public function computeVatFromNet(TaxCode $code, string $net_amount): array
    {
        if ($code->kind !== TaxKind::Vat) {
            throw TaxKindMismatchException::expected(TaxKind::Vat->value, $code->kind->value);
        }

        $net = BigDecimal::of($net_amount);
        $rate = BigDecimal::of((string) $code->rate);
        $tax = $net->multipliedBy($rate)->dividedBy('100', 4, RoundingMode::HALF_UP);
        $gross = $net->plus($tax);

        return [
            'taxable' => $net->toScale(4)->__toString(),
            'tax' => $tax->toScale(4)->__toString(),
            'gross' => $gross->toScale(4)->__toString(),
        ];
    }

    /**
     * Withholding applied on a gross base (common for labour withholdings).
     *
     * @return array{tax: string, net: string}
     */
    public function computeWithholdingFromGross(TaxCode $code, string $gross_amount): array
    {
        if ($code->kind !== TaxKind::Withholding) {
            throw TaxKindMismatchException::expected(TaxKind::Withholding->value, $code->kind->value);
        }

        $gross = BigDecimal::of($gross_amount);
        $rate = BigDecimal::of((string) $code->rate);
        $tax = $gross->multipliedBy($rate)->dividedBy('100', 4, RoundingMode::HALF_UP);

        return [
            'tax' => $tax->toScale(4)->__toString(),
            'net' => $gross->minus($tax)->toScale(4)->__toString(),
        ];
    }

    /**
     * @return array{tax_code: string, tax_rate: string, tax_label: string}
     */
    public function snapshotForLine(TaxCode $code): array
    {
        return [
            'tax_code' => $code->code,
            'tax_rate' => (string) $code->rate,
            'tax_label' => $code->label,
        ];
    }
}
