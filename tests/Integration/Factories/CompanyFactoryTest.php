<?php

declare(strict_types=1);

/**
 * `Company` is the tenant root of the ERP domain: every other factory hangs off it, so it is the
 * first one that has to be trustworthy. These assertions pin the two things a caller relies on —
 * a persisted row whose fiscal identity is populated, and settings that resolve against it.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Company\ErpCompanySettings;

uses(RefreshDatabase::class);

it('persists a company with a populated fiscal identity', function (): void {
    $company = Company::factory()->create();

    expect($company->exists)->toBeTrue()
        ->and($company->slug)->not->toBeEmpty()
        ->and($company->name)->not->toBeEmpty()
        ->and($company->fiscal_country)->toHaveLength(2)
        ->and($company->default_currency)->toHaveLength(3);

    expect(Company::query()->whereKey($company->getKey())->exists())->toBeTrue();
});

it('gives every company a distinct slug', function (): void {
    $slugs = Company::factory()->count(3)->create()->pluck('slug');

    expect($slugs->unique())->toHaveCount(3);
});

it('produces a company whose settings resolve', function (): void {
    $company = Company::factory()->create();

    $settings = app(ErpCompanySettings::class);

    expect($settings->mergeWithDefaults($company))->toBeArray()
        ->and($settings->priceTolerancePercent($company))->toBeFloat();
});

it('marks a company as the default tenant only when asked', function (): void {
    expect(Company::factory()->create()->is_default)->toBeFalse()
        ->and(Company::factory()->default()->create()->is_default)->toBeTrue();
});
