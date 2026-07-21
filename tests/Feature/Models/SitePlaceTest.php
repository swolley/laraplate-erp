<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Place;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Site;

uses(RefreshDatabase::class);

it('links an ERP site to the canonical Core place', function (): void {
    $company = Company::query()->create([
        'slug' => 'site-place-'.uniqid(), 'name' => 'Site Place',
        'fiscal_country' => 'IT', 'default_currency' => 'EUR',
    ]);
    $place = Place::query()->create([
        'address' => 'Via Roma 10', 'postcode' => '20100', 'city' => 'Milano',
        'province' => 'MI', 'country' => 'IT',
    ]);
    $site = Site::query()->create([
        'company_id' => $company->id, 'name' => 'Headquarters', 'place_id' => $place->id,
        'valid_from' => now(),
    ]);

    expect($site->place)->toBeInstanceOf(Place::class)
        ->and($site->place->address)->toBe('Via Roma 10')
        ->and($site->place->city)->toBe('Milano');
});
