<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Services\Diagnostics\ErpHealthCheckService;
use Symfony\Component\Console\Command\Command;

uses(RefreshDatabase::class);

it('discovers the ERP health check command', function (): void {
    expect(Artisan::all())->toHaveKey('erp:health-check');
});

it('reports failures without mutating an unconfigured ERP installation', function (): void {
    Company::query()->withoutGlobalScopes()->update(['is_default' => false]);
    $companies_before = Company::query()->withoutGlobalScopes()->count();
    $result = app(ErpHealthCheckService::class)->run();

    expect($result['summary']['failure'])->toBeGreaterThan(0)
        ->and($result['checks'][0]['key'])->toBe('default_company')
        ->and($result['checks'][0]['status'])->toBe('failure');

    $this->artisan('erp:health-check', ['--format' => 'json'])
        ->expectsOutputToContain('"default_company"')
        ->assertExitCode(Command::FAILURE);

    expect(Company::query()->withoutGlobalScopes()->count())->toBe($companies_before);
});

it('passes for a bootstrapped ERP installation', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    $company = Company::getDefault();
    expect($company)->not->toBeNull();

    DocumentSequence::query()->withoutGlobalScopes()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesInvoice,
        'fiscal_year' => now()->year,
        'last_number' => 0,
        'gap_allowed' => false,
        'prefix' => '',
        'padding' => 5,
        'suffix' => '',
    ]);

    config()->set('erp.einvoice.driver', 'stub');

    $this->artisan('erp:health-check', ['--format' => 'json'])
        ->expectsOutputToContain('"failure": 0')
        ->assertSuccessful();
});

it('rejects incomplete Aruba configuration', function (): void {
    config()->set('erp.einvoice.driver', 'aruba');
    config()->set('erp.einvoice.aruba.base_url', null);
    config()->set('erp.einvoice.aruba.token', null);
    config()->set('erp.einvoice.aruba.username', null);
    config()->set('erp.einvoice.aruba.password', null);

    $this->artisan('erp:health-check', ['--format' => 'json'])
        ->expectsOutputToContain('Aruba requires a base URL')
        ->assertExitCode(Command::FAILURE);
});
