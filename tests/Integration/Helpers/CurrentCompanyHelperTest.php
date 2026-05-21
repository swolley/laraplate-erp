<?php

declare(strict_types=1);

use function Modules\ERP\Helpers\current_company_id;
use function Modules\ERP\Helpers\with_company;

it('returns null when no company context is active', function (): void {
    app()->forgetInstance('erp.current_company_id');

    expect(current_company_id())->toBeNull();
});

it('returns the bound company id when explicitly set in the container', function (): void {
    app()->instance('erp.current_company_id', 42);

    try {
        expect(current_company_id())->toBe(42);
    } finally {
        app()->forgetInstance('erp.current_company_id');
    }
});

it('coerces numeric strings bound in the container to integers', function (): void {
    app()->instance('erp.current_company_id', '7');

    try {
        expect(current_company_id())->toBe(7);
    } finally {
        app()->forgetInstance('erp.current_company_id');
    }
});

it('with_company scopes the company id only inside the callback', function (): void {
    app()->forgetInstance('erp.current_company_id');

    expect(current_company_id())->toBeNull();

    $insideValue = with_company(99, fn (): ?int => current_company_id());

    expect($insideValue)->toBe(99)
        ->and(current_company_id())->toBeNull();
});

it('with_company restores the previous company id after the callback', function (): void {
    app()->instance('erp.current_company_id', 5);

    try {
        with_company(11, function (): void {
            expect(current_company_id())->toBe(11);
        });

        expect(current_company_id())->toBe(5);
    } finally {
        app()->forgetInstance('erp.current_company_id');
    }
});
