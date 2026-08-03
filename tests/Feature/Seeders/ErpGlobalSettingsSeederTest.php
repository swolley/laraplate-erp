<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Setting;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Services\Company\ErpCompanySettings;

uses(RefreshDatabase::class);

it('seeds global ERP settings stamped with the ERP module', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    $names = collect(ErpCompanySettings::globalSettingDefinitions())->pluck('name');

    $settings = Setting::query()->withoutGlobalScopes()->whereIn('name', $names)->get();

    expect($settings)->toHaveCount($names->count())
        ->and($settings->pluck('module')->unique()->all())->toBe(['ERP']);
});

it('is idempotent and leaves an operator-changed value untouched on a second run', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    Setting::query()->withoutGlobalScopes()
        ->where('name', ErpCompanySettings::PRICE_TOLERANCE_PERCENT)
        ->update(['value' => json_encode(12.5), 'description' => 'drifted']);

    $this->seed(ERPDatabaseSeeder::class);

    $setting = Setting::query()->withoutGlobalScopes()
        ->where('name', ErpCompanySettings::PRICE_TOLERANCE_PERCENT)->sole();

    expect($setting->value)->toBe(12.5)
        ->and($setting->description)->toBe('Three-way match price tolerance (percent)');
});
