<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Seeding\SeedGraphBuilder;
use Modules\Core\Seeding\SeedNode;
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
    // structural edge assertion below.
    $this->seed(ERPDatabaseSeeder::class);
    $this->seed(ItalianTaxCodesSeeder::class);

    $company = Company::query()->withoutGlobalScopes()->where('is_default', true)->firstOrFail();

    expect(TaxCode::query()->withoutGlobalScopes()->where('company_id', $company->id)->count())
        ->toBeGreaterThan(0);
});

it('runs after ERPDatabaseSeeder in the seed graph, not by alphabetical tie-break', function (): void {
    // This once worked only because 'E' sorts before 'I' in the graph's deterministic
    // tie-break: run() warns and returns when the default company is missing, so a dropped
    // edge would silently seed nothing instead of failing.
    $node = collect(app(SeedGraphBuilder::class)->build())
        ->firstOrFail(fn (SeedNode $node): bool => $node->seederClass === ItalianTaxCodesSeeder::class);

    expect($node->dependsOn)->toContain(ERPDatabaseSeeder::class);
});
