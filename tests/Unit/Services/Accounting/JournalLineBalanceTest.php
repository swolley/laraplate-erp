<?php

declare(strict_types=1);

use Modules\Business\Exceptions\UnbalancedJournalException;
use Modules\Business\Services\Accounting\JournalLineBalance;

it('accepts balanced signed amounts', function (): void {
    expect(fn () => JournalLineBalance::assertBalanced(['100.0000', '-50.25', '-49.7500']))
        ->not->toThrow(\Throwable::class);
});

it('throws when amounts do not net to zero', function (): void {
    expect(fn () => JournalLineBalance::assertBalanced(['10', '-5']))
        ->toThrow(UnbalancedJournalException::class);
});
