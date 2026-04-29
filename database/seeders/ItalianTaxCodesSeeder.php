<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Seeders;

use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\TaxKind;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\TaxCode;
use Modules\Core\Overrides\Seeder;

/**
 * Dev / default Italian VAT and sample withholding rows for a {@see Company}.
 *
 * Rate changes must add **new** {@see TaxCode} rows; never mutate historical codes.
 */
final class ItalianTaxCodesSeeder extends Seeder
{
    /**
     * @var list<array{code: string, kind: TaxKind, rate: string, label: string}>
     */
    private const array ROWS = [
        ['code' => 'IT_VAT_22', 'kind' => TaxKind::Vat, 'rate' => '22.0000', 'label' => 'IVA 22%'],
        ['code' => 'IT_VAT_10', 'kind' => TaxKind::Vat, 'rate' => '10.0000', 'label' => 'IVA 10%'],
        ['code' => 'IT_VAT_4', 'kind' => TaxKind::Vat, 'rate' => '4.0000', 'label' => 'IVA 4%'],
        ['code' => 'IT_VAT_0', 'kind' => TaxKind::Vat, 'rate' => '0.0000', 'label' => 'IVA 0% / esente'],
        ['code' => 'IT_WH_SAMPLE_23', 'kind' => TaxKind::Withholding, 'rate' => '23.0000', 'label' => 'Sample withholding 23% on gross (illustrative)'],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('tax_codes')) {
            $this->command?->warn('ItalianTaxCodesSeeder skipped: tax_codes table is missing.');

            return;
        }

        $company = Company::query()->withoutGlobalScopes()->where('is_default', true)->orderBy('id')->first();

        if (! $company instanceof Company) {
            $this->command?->warn('ItalianTaxCodesSeeder skipped: no default company.');

            return;
        }

        $this->seedForCompany($company);
    }

    public function seedForCompany(Company $company): void
    {
        $effective = '2000-01-01';

        foreach (self::ROWS as $row) {
            TaxCode::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $row['code'],
                ],
                [
                    'kind' => $row['kind'],
                    'country' => 'IT',
                    'rate' => $row['rate'],
                    'label' => $row['label'],
                    'is_active' => true,
                    'effective_from' => $effective,
                    'replaced_by_tax_code_id' => null,
                    'meta' => null,
                ],
            );
        }

        $this->command?->line('    - Italian tax codes ensured for company ' . $company->id);
    }
}
