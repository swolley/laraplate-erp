<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\ERP\Casts\PaymentRunStatus;
use Modules\ERP\Filament\Resources\PaymentRuns\Actions\PaymentRunActions;
use Modules\ERP\Filament\Resources\PaymentRuns\PaymentRunResource;
use Modules\ERP\Models\PaymentRun;

uses(RefreshDatabase::class);

it('registers Filament pages for payment runs', function (): void {
    expect(PaymentRunResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(PaymentRunResource::getModel())->toBe(PaymentRun::class);
});

it('defines payment run approve export and cancel actions', function (): void {
    expect(PaymentRunActions::approve()->getName())->toBe('approve')
        ->and(PaymentRunActions::exportSepa()->getName())->toBe('export_sepa')
        ->and(PaymentRunActions::exportCbiBonifici()->getName())->toBe('export_cbi_bonifici')
        ->and(PaymentRunActions::cancel()->getName())->toBe('cancel');
});

it('gates payment run resource edits after export', function (): void {
    // PaymentRun is now governed by ERPModelPolicy, so canEdit() consults the
    // Gate through Filament's own panel guard. A superadmin short-circuits the
    // permission check, leaving the status rule as the only thing under test —
    // which is what this test is about.
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate('superadmin', config('auth.defaults.guard')));
    $this->actingAs($actor, 'admin');

    $draft = new PaymentRun(['status' => PaymentRunStatus::Draft]);
    $exported = new PaymentRun(['status' => PaymentRunStatus::Exported]);

    expect(PaymentRunResource::canEdit($draft))->toBeTrue()
        ->and(PaymentRunResource::canEdit($exported))->toBeFalse();
});

it('denies payment run edits to a user without the update permission', function (): void {
    $this->actingAs(User::factory()->create(), 'admin');

    expect(PaymentRunResource::canEdit(new PaymentRun(['status' => PaymentRunStatus::Draft])))->toBeFalse();
});
