<?php

declare(strict_types=1);

use Modules\ERP\Exceptions\FiscalPeriodAlreadyClosedException;
use Modules\ERP\Exceptions\FiscalYearAlreadyClosedException;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;
use Modules\ERP\Tests\Support\FakeFiscalPeriod;

it('closes an open fiscal period and persists in memory', function (): void {
    $period = new FakeFiscalPeriod();
    $period->is_closed = false;
    $period->setSkipValidation(true);

    (new FiscalPeriodCloser())->closePeriod($period);

    expect($period->is_closed)->toBeTrue()
        ->and($period->saved)->toBeTrue();
});

it('throws when closing a period already marked closed', function (): void {
    $period = new FakeFiscalPeriod();
    $period->is_closed = true;
    $period->forceFill(['id' => 99]);

    expect(fn () => (new FiscalPeriodCloser())->closePeriod($period))
        ->toThrow(FiscalPeriodAlreadyClosedException::class);
});

it('throws when closing a year already marked closed', function (): void {
    $year = new FiscalYear();
    $year->is_closed = true;
    $year->forceFill(['id' => 1]);
    $year->setSkipValidation(true);

    expect(fn () => (new FiscalPeriodCloser())->closeYear($year))
        ->toThrow(FiscalYearAlreadyClosedException::class);
});
