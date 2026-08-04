<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Database\Seeders\ItalianTaxCodesSeeder;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\TaxCode;

uses(RefreshDatabase::class);

it('seeds Italian tax codes for the default company when run in graph order after ERPDatabaseSeeder', function (): void {
    // ItalianTaxCodesSeeder::dependsOn() declares ERPDatabaseSeeder because
    // run() looks up the default company ERPDatabaseSeeder::ensureDefaultCompany()
    // creates. Seeding in that order (as the graph does) is what distinguishes
    // "ran and seeded" from "ran and silently warned-and-returned" — see the
    // structural edge assertion in SeedGraphBuilderTest.
    $this->seed(ERPDatabaseSeeder::class);
    $this->seed(ItalianTaxCodesSeeder::class);

    $company = Company::query()->withoutGlobalScopes()->where('is_default', true)->firstOrFail();

    expect(TaxCode::query()->withoutGlobalScopes()->where('company_id', $company->id)->count())
        ->toBeGreaterThan(0);
});
