<?php

declare(strict_types=1);

use Modules\Business\Filament\Resources\Accounts\AccountResource;
use Modules\Business\Filament\Resources\Companies\CompanyResource;
use Modules\Business\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Modules\Business\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use Modules\Business\Filament\Resources\FiscalYears\FiscalYearResource;
use Modules\Business\Filament\Resources\JournalEntries\JournalEntryResource;
use Modules\Business\Filament\Resources\TaxCodes\TaxCodeResource;
use Modules\Business\Models\Account;
use Modules\Business\Models\Company;
use Modules\Business\Models\DocumentSequence;
use Modules\Business\Models\FiscalPeriod;
use Modules\Business\Models\FiscalYear;
use Modules\Business\Models\JournalEntry;
use Modules\Business\Models\TaxCode;
use Tests\TestCase;

uses(TestCase::class);

it('registers Filament pages for companies', function (): void {
    $pages = CompanyResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('binds company resource to Company model', function (): void {
    expect(CompanyResource::getModel())->toBe(Company::class);
});

it('registers Filament pages for tax codes', function (): void {
    $pages = TaxCodeResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('binds tax code resource to TaxCode model', function (): void {
    expect(TaxCodeResource::getModel())->toBe(TaxCode::class);
});

it('registers Filament pages for accounts', function (): void {
    expect(AccountResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(AccountResource::getModel())->toBe(Account::class);
});

it('registers Filament pages for journal entries including view', function (): void {
    expect(JournalEntryResource::getPages())->toHaveKeys(['index', 'create', 'view', 'edit'])
        ->and(JournalEntryResource::getModel())->toBe(JournalEntry::class);
});

it('disallows editing posted journal entries via resource gate', function (): void {
    $draft = new JournalEntry(['posted_at' => null]);
    $posted = new JournalEntry(['posted_at' => now()]);

    expect(JournalEntryResource::canEdit($draft))->toBeTrue()
        ->and(JournalEntryResource::canEdit($posted))->toBeFalse();
});

it('registers Filament pages for fiscal years', function (): void {
    expect(FiscalYearResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(FiscalYearResource::getModel())->toBe(FiscalYear::class);
});

it('registers Filament pages for fiscal periods', function (): void {
    expect(FiscalPeriodResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(FiscalPeriodResource::getModel())->toBe(FiscalPeriod::class);
});

it('registers Filament pages for document sequences', function (): void {
    expect(DocumentSequenceResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(DocumentSequenceResource::getModel())->toBe(DocumentSequence::class);
});
