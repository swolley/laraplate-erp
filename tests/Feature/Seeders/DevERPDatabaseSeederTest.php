<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Database\Seeders\DevERPDatabaseSeeder;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Activity;
use Modules\ERP\Models\OpportunityStage;

uses(RefreshDatabase::class);

it('seeds opportunity stages and activities without hitting missing attributes', function (): void {
    $this->seed(ERPDatabaseSeeder::class);
    $this->seed(DevERPDatabaseSeeder::class);

    expect(OpportunityStage::query()->withoutGlobalScopes()->count())->toBeGreaterThan(0)
        ->and(Activity::query()->withoutGlobalScopes()->count())->toBeGreaterThan(0);
});

it('is idempotent: a second dev run adds no duplicates', function (): void {
    $this->seed(ERPDatabaseSeeder::class);
    $this->seed(DevERPDatabaseSeeder::class);
    $stages = OpportunityStage::query()->withoutGlobalScopes()->count();

    $this->seed(DevERPDatabaseSeeder::class);

    expect(OpportunityStage::query()->withoutGlobalScopes()->count())->toBe($stages);
});
