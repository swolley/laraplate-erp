<?php

declare(strict_types=1);

use Modules\ERP\Filament\Resources\DeliveryNotes\Actions\DeliveryNotePostingActions;
use Modules\ERP\Filament\Resources\DocumentSequences\Actions\DocumentSequenceActions;
use Modules\ERP\Filament\Resources\FiscalPeriods\Actions\FiscalPeriodActions;
use Modules\ERP\Filament\Resources\FiscalYears\Actions\FiscalYearActions;
use Modules\ERP\Filament\Resources\JournalEntries\Actions\JournalEntryActions;
use Modules\ERP\Filament\Resources\Quotations\Actions\QuotationActions;
use Modules\ERP\Filament\Resources\SalesOrders\Actions\SalesOrderAmendmentActions;

it('exposes Phase 2A Filament domain action factories', function (): void {
    expect(class_exists(FiscalPeriodActions::class))->toBeTrue()
        ->and(class_exists(FiscalYearActions::class))->toBeTrue()
        ->and(class_exists(DeliveryNotePostingActions::class))->toBeTrue()
        ->and(class_exists(DocumentSequenceActions::class))->toBeTrue()
        ->and(class_exists(JournalEntryActions::class))->toBeTrue()
        ->and(class_exists(QuotationActions::class))->toBeTrue()
        ->and(class_exists(SalesOrderAmendmentActions::class))->toBeTrue()
        ->and(FiscalPeriodActions::close()->getName())->toBe('close_period')
        ->and(DocumentSequenceActions::reset()->getName())->toBe('reset')
        ->and(JournalEntryActions::reverse()->getName())->toBe('reverse')
        ->and(QuotationActions::unlock()->getName())->toBe('unlock')
        ->and(QuotationActions::createRevision()->getName())->toBe('create_revision');
});
