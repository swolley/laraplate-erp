<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Accounting\ChartOfAccountsInstaller;
use Modules\ERP\Tests\Stubs\ArrayChartOfAccountsProviderStub;
use Modules\ERP\Tests\Stubs\ChartOfAccountsInstallerTestStub;

uses(RefreshDatabase::class);

function bindChartProvider(ArrayChartOfAccountsProviderStub $provider): ChartOfAccountsInstaller
{
    app()->instance(ArrayChartOfAccountsProviderStub::class, $provider);
    app()->bind(
        ChartOfAccountsInstaller::class,
        static fn (): ChartOfAccountsInstaller => new ChartOfAccountsInstaller($provider),
    );

    return app(ChartOfAccountsInstaller::class);
}

it('installs a chart when the company has no accounts', function (): void {
    $company = Company::query()->create([
        'slug' => 'coa-install-' . uniqid(),
        'name' => 'COA Install Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $installer = bindChartProvider(new ArrayChartOfAccountsProviderStub([
        ['code' => '1000', 'name' => 'Cash', 'kind' => AccountKind::Asset, 'parent_code' => null],
        ['code' => '1100', 'name' => 'Bank', 'kind' => AccountKind::Asset, 'parent_code' => '1000'],
    ]));

    $installer->installWhenEmpty($company);

    expect(Account::query()->where('company_id', $company->id)->count())->toBe(2)
        ->and(Account::query()->where('code', '1100')->value('parent_id'))->not->toBeNull();
});

it('skips installation when accounts already exist', function (): void {
    $company = Company::query()->create([
        'slug' => 'coa-skip-' . uniqid(),
        'name' => 'COA Skip Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    Account::query()->create([
        'company_id' => $company->id,
        'code' => '1000',
        'name' => 'Existing',
        'kind' => AccountKind::Asset->value,
        'is_active' => true,
    ]);
    $installer = bindChartProvider(new ArrayChartOfAccountsProviderStub([
        ['code' => '2000', 'name' => 'Liability', 'kind' => AccountKind::Liability, 'parent_code' => null],
    ]));

    $installer->installWhenEmpty($company);

    expect(Account::query()->where('company_id', $company->id)->count())->toBe(1);
});

it('rejects duplicate account codes in chart definitions', function (): void {
    $company = Company::query()->create([
        'slug' => 'coa-dup-' . uniqid(),
        'name' => 'COA Dup Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $installer = bindChartProvider(new ArrayChartOfAccountsProviderStub([
        ['code' => '1000', 'name' => 'Cash A', 'kind' => AccountKind::Asset, 'parent_code' => null],
        ['code' => '1000', 'name' => 'Cash B', 'kind' => AccountKind::Asset, 'parent_code' => null],
    ]));

    expect(fn () => $installer->installWhenEmpty($company))
        ->toThrow(\InvalidArgumentException::class, 'Duplicate account code');
});

it('rejects missing parent references during installation', function (): void {
    $company = Company::query()->create([
        'slug' => 'coa-parent-' . uniqid(),
        'name' => 'COA Parent Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $provider = new ArrayChartOfAccountsProviderStub([]);
    $installer = new ChartOfAccountsInstallerTestStub($provider, [
        ['code' => '1100', 'name' => 'Child', 'kind' => AccountKind::Asset, 'parent_code' => '9999'],
    ]);

    expect(fn () => $installer->installWithForcedSort($company))
        ->toThrow(\InvalidArgumentException::class, 'Parent code "9999" not found');
});

it('rejects cyclic parent references in chart definitions', function (): void {
    $company = Company::query()->create([
        'slug' => 'coa-cycle-' . uniqid(),
        'name' => 'COA Cycle Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $installer = bindChartProvider(new ArrayChartOfAccountsProviderStub([
        ['code' => '1000', 'name' => 'Root', 'kind' => AccountKind::Asset, 'parent_code' => null],
        ['code' => '2000', 'name' => 'A', 'kind' => AccountKind::Asset, 'parent_code' => '3000'],
        ['code' => '3000', 'name' => 'B', 'kind' => AccountKind::Asset, 'parent_code' => '2000'],
    ]));

    expect(fn () => $installer->installWhenEmpty($company))
        ->toThrow(\InvalidArgumentException::class, 'cycle or a missing parent_code');
});

it('writes chart accounts on the company affinity connection', function (): void {
    config()->set('database.connections.erp-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    $connection = Schema::connection('erp-secondary');
    $connection->create((new Account)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('code');
        $table->string('name');
        $table->string('kind');
        $table->unsignedInteger('parent_id')->nullable();
        $table->json('meta')->nullable();
        $table->boolean('is_active');
        $table->timestamps();
    });

    $company = (new Company)->setConnection('erp-secondary');
    $company->id = 9876;
    $default_company = new Company([
        'slug' => 'default-affinity-company',
        'name' => 'Default affinity company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $default_company->id = 9876;
    $default_company->setSkipValidation(true);
    $default_company->save();
    $seed_company = new Company([
        'slug' => 'primary-seed-company',
        'name' => 'Primary seed company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $seed_company->id = 1;
    $seed_company->setSkipValidation(true);
    $seed_company->save();
    Account::query()->getQuery()->insert([
        'id' => 1,
        'company_id' => 1,
        'code' => 'PRIMARY-SEED',
        'name' => 'Primary seed',
        'kind' => AccountKind::Asset->value,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $events = [];
    $company->getConnection()->listen(function () use (&$events): void {
        $events[] = 'query';
    });
    $installer = bindChartProvider(new ArrayChartOfAccountsProviderStub([
        ['code' => '1000', 'name' => 'Secondary cash', 'kind' => AccountKind::Asset, 'parent_code' => null],
    ]));

    $installer->installWhenEmpty($company);

    expect($company->getConnection()->table((new Account)->getTable())->where('company_id', 9876)->exists())->toBeTrue()
        ->and(Account::query()->where('company_id', 9876)->count())->toBe(0)
        ->and($events)->not->toBeEmpty();
});
