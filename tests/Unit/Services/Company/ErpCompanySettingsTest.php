<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Services\SettingsCacheCoordinator;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Company\ErpCompanySettings;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(SettingsCacheCoordinator::class)->flushAll();
});

it('exposes conservative default erp settings', function (): void {
    $defaults = ErpCompanySettings::defaultSettings();

    expect($defaults)->toHaveKey('erp')
        ->and(data_get($defaults, ErpCompanySettings::PRICE_TOLERANCE_PERCENT))->toBe(0)
        ->and(data_get($defaults, ErpCompanySettings::QTY_TOLERANCE_PERCENT))->toBe(0)
        ->and(data_get($defaults, ErpCompanySettings::INVOICE_GENERATION_MODE))
        ->toBe(ErpCompanySettings::INVOICE_GENERATION_MODE_EXPANDED);
});

it('merges defaults without overwriting existing company values', function (): void {
    $company = Company::query()->create([
        'slug' => 'settings-merge',
        'name' => 'Settings Merge',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $company->settings = [
        'erp' => [
            'three_way_match' => [
                'price_tolerance_percent' => 3,
            ],
        ],
    ];
    $company->save();

    $service = app(ErpCompanySettings::class);
    $merged = $service->mergeWithDefaults($company);

    expect(data_get($merged, ErpCompanySettings::PRICE_TOLERANCE_PERCENT))->toBe(3)
        ->and(data_get($merged, ErpCompanySettings::QTY_TOLERANCE_PERCENT))->toBe(0)
        ->and(data_get($merged, ErpCompanySettings::INVOICE_GENERATION_MODE))
        ->toBe(ErpCompanySettings::INVOICE_GENERATION_MODE_EXPANDED);
});

it('reads three-way match tolerances from company settings json', function (): void {
    $company = Company::query()->create([
        'slug' => 'settings-co',
        'name' => 'Settings Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $company->settings = [
        'erp' => [
            'three_way_match' => [
                'price_tolerance_percent' => 5.5,
                'qty_tolerance_percent' => 2.0,
            ],
        ],
    ];
    $company->save();

    $service = app(ErpCompanySettings::class);

    expect($service->priceTolerancePercent($company))->toBe(5.5)
        ->and($service->qtyTolerancePercent($company))->toBe(2.0);
});

it('falls back to global setting rows when company json omits the key', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => ErpCompanySettings::PRICE_TOLERANCE_PERCENT,
        'value' => 4.5,
        'type' => SettingTypeEnum::Float,
        'group_name' => ErpCompanySettings::GLOBAL_SETTINGS_GROUP,
        'description' => 'test',
    ]);

    app(PerModelSettingResolver::class)->flush();

    $company = Company::query()->create([
        'slug' => 'settings-global',
        'name' => 'Settings Global',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $service = app(ErpCompanySettings::class);

    expect($service->priceTolerancePercent($company))->toBe(4.5);
});

it('defaults three-way match tolerances to zero when settings are missing', function (): void {
    $company = Company::query()->create([
        'slug' => 'settings-default',
        'name' => 'Settings Default',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $service = app(ErpCompanySettings::class);

    expect($service->priceTolerancePercent($company))->toBe(0.0)
        ->and($service->qtyTolerancePercent($company))->toBe(0.0);
});
