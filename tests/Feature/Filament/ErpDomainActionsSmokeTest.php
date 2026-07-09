<?php

declare(strict_types=1);

use Modules\ERP\Filament\Resources\DeliveryNotes\Actions\DeliveryNotePostingActions;
use Modules\ERP\Filament\Resources\FiscalPeriods\Actions\FiscalPeriodActions;
use Modules\ERP\Filament\Resources\FiscalYears\Actions\FiscalYearActions;
use Modules\ERP\Filament\Resources\JournalEntries\Actions\JournalEntryActions;
use Modules\ERP\Filament\Resources\SalesOrders\Actions\SalesOrderAmendmentActions;

it('exposes Phase 2A Filament domain action factories', function (): void {
    expect(class_exists(FiscalPeriodActions::class))->toBeTrue()
        ->and(class_exists(FiscalYearActions::class))->toBeTrue()
        ->and(class_exists(DeliveryNotePostingActions::class))->toBeTrue()
        ->and(class_exists(JournalEntryActions::class))->toBeTrue()
        ->and(class_exists(SalesOrderAmendmentActions::class))->toBeTrue()
        ->and(FiscalPeriodActions::close()->getName())->toBe('close_period')
        ->and(JournalEntryActions::reverse()->getName())->toBe('reverse');
});
