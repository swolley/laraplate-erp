<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
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
    $draft = new PaymentRun(['status' => PaymentRunStatus::Draft]);
    $exported = new PaymentRun(['status' => PaymentRunStatus::Exported]);

    expect(PaymentRunResource::canEdit($draft))->toBeTrue()
        ->and(PaymentRunResource::canEdit($exported))->toBeFalse();
});
