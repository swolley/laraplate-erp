<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
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
