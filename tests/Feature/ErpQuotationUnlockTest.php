<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Policies\ERPModelPolicy;

uses(RefreshDatabase::class);

function quotationUnlockRecord(bool $locked = false): Quotation
{
    $company = Company::query()->create([
        'slug' => 'q-unlock-' . uniqid(),
        'name' => 'Q Unlock Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Quotation Customer',
        'is_customer' => true,
    ]);
    $quotation = Quotation::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => QuoteStatus::Draft,
    ]);

    if ($locked) {
        $quotation->lock();
    }

    return $quotation->refresh();
}

function quotationUnlockGuard(): string
{
    $guard = config('auth.defaults.guard');

    return is_string($guard) ? $guard : 'web';
}

function quotationUnlockPermission(Quotation $quotation): string
{
    return sprintf(
        '%s.%s.%s',
        $quotation->getConnectionName() ?? 'default',
        $quotation->getTable(),
        'unlock',
    );
}

it('seeds quotation unlock permission', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_quotations.unlock')->exists())->toBeTrue();
});

it('denies unlock when quotation is not locked', function (): void {
    $quotation = quotationUnlockRecord();
    $user = User::factory()->create();
    $permission = quotationUnlockPermission($quotation);

    $guard = quotationUnlockGuard();
    Permission::findOrCreate($permission, $guard);
    $user->givePermissionTo($permission);

    expect(app(ERPModelPolicy::class)->unlock($user, $quotation))->toBeFalse();
});

it('allows unlock when quotation is locked and user has permission', function (): void {
    $quotation = quotationUnlockRecord(locked: true);
    $user = User::factory()->create();
    $permission = quotationUnlockPermission($quotation);

    $guard = quotationUnlockGuard();
    Permission::findOrCreate($permission, $guard);
    $user->givePermissionTo($permission);

    expect($quotation->isLocked())->toBeTrue()
        ->and($user->hasPermissionTo($permission, $guard))->toBeTrue()
        ->and(app(ERPModelPolicy::class)->unlock($user, $quotation))->toBeTrue();
});

it('denies unlock when user lacks permission', function (): void {
    $quotation = quotationUnlockRecord(locked: true);
    $user = User::factory()->create();

    Permission::findOrCreate(quotationUnlockPermission($quotation), quotationUnlockGuard());

    expect(app(ERPModelPolicy::class)->unlock($user, $quotation))->toBeFalse();
});
