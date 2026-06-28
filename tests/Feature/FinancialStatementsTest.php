<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\JournalEntryLine;
use Modules\ERP\Services\Reporting\BalanceSheetService;
use Modules\ERP\Services\Reporting\FinancialReportCsvExporter;
use Modules\ERP\Services\Reporting\IncomeStatementService;
use Modules\ERP\Services\Reporting\TrialBalanceService;

uses(RefreshDatabase::class);

function createTestCompany(): Company
{
    return Company::query()->create([
        'slug' => 'test-co-' . uniqid(),
        'name' => 'Test Company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);
}

function createAccount(Company $company, string $code, string $name, AccountKind $kind): Account
{
    return Account::query()->create([
        'company_id' => $company->id,
        'code' => $code,
        'name' => $name,
        'kind' => $kind->value,
        'is_active' => true,
    ]);
}

function postBalancedEntry(Company $company, array $lines, ?CarbonImmutable $posted_at = null): JournalEntry
{
    $posted_at ??= CarbonImmutable::now();

    $entry = JournalEntry::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'posted_at' => $posted_at,
        'description' => 'Test entry',
    ]);

    $line_no = 1;
    foreach ($lines as $line) {
        JournalEntryLine::query()->create([
            'journal_entry_id' => $entry->getKey(),
            'line_no' => $line_no,
            'account_id' => $line['account_id'],
            'amount_doc' => $line['amount_local'],
            'currency_doc' => 'EUR',
            'amount_local' => $line['amount_local'],
            'currency_local' => 'EUR',
            'fx_rate' => '1.0000',
            'description' => $line['description'] ?? null,
        ]);
        $line_no++;
    }

    return $entry;
}

it('trial balance debits equal credits', function (): void {
    $company = createTestCompany();
    $cash = createAccount($company, '1000', 'Cash', AccountKind::Asset);
    $revenue = createAccount($company, '4000', 'Sales Revenue', AccountKind::Revenue);
    $expense = createAccount($company, '5000', 'Office Supplies', AccountKind::Expense);
    $liability = createAccount($company, '2000', 'Accounts Payable', AccountKind::Liability);

    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '1000.0000'],
        ['account_id' => $revenue->id, 'amount_local' => '-1000.0000'],
    ]);

    postBalancedEntry($company, [
        ['account_id' => $expense->id, 'amount_local' => '200.0000'],
        ['account_id' => $cash->id, 'amount_local' => '-200.0000'],
    ]);

    postBalancedEntry($company, [
        ['account_id' => $expense->id, 'amount_local' => '300.0000'],
        ['account_id' => $liability->id, 'amount_local' => '-300.0000'],
    ]);

    $service = new TrialBalanceService;
    $result = $service->generate((int) $company->id, CarbonImmutable::now());

    $total_debit = 0.0;
    $total_credit = 0.0;
    foreach ($result as $row) {
        $total_debit += (float) $row['debit'];
        $total_credit += (float) $row['credit'];
    }

    expect(number_format($total_debit, 4, '.', ''))
        ->toBe(number_format($total_credit, 4, '.', ''));
});

it('trial balance only includes posted entries up to date', function (): void {
    $company = createTestCompany();
    $cash = createAccount($company, '1000', 'Cash', AccountKind::Asset);
    $revenue = createAccount($company, '4000', 'Sales Revenue', AccountKind::Revenue);

    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '500.0000'],
        ['account_id' => $revenue->id, 'amount_local' => '-500.0000'],
    ], CarbonImmutable::parse('2026-01-15'));

    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '700.0000'],
        ['account_id' => $revenue->id, 'amount_local' => '-700.0000'],
    ], CarbonImmutable::parse('2026-03-10'));

    // Unposted entry (posted_at = null) — should be excluded
    $unposted = JournalEntry::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'posted_at' => null,
        'description' => 'Draft entry',
    ]);
    JournalEntryLine::query()->create([
        'journal_entry_id' => $unposted->getKey(),
        'line_no' => 1,
        'account_id' => $cash->id,
        'amount_doc' => '9999.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '9999.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.0000',
    ]);

    $service = new TrialBalanceService;
    $result = $service->generate((int) $company->id, CarbonImmutable::parse('2026-02-28'));

    $cash_row = collect($result)->firstWhere('account_code', '1000');

    expect($cash_row)->not->toBeNull()
        ->and($cash_row['balance'])->toBe('500.0000');
});

it('balance sheet is balanced (assets = liabilities + equity + net income)', function (): void {
    $company = createTestCompany();
    $cash = createAccount($company, '1000', 'Cash', AccountKind::Asset);
    $equipment = createAccount($company, '1500', 'Equipment', AccountKind::Asset);
    $liability = createAccount($company, '2000', 'Loans Payable', AccountKind::Liability);
    $equity_acc = createAccount($company, '3000', 'Share Capital', AccountKind::Equity);
    $revenue = createAccount($company, '4000', 'Service Revenue', AccountKind::Revenue);
    $expense = createAccount($company, '5000', 'Rent Expense', AccountKind::Expense);

    // Initial capital injection: cash 10000, equity 10000
    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '10000.0000'],
        ['account_id' => $equity_acc->id, 'amount_local' => '-10000.0000'],
    ]);

    // Take loan: cash 5000, liability 5000
    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '5000.0000'],
        ['account_id' => $liability->id, 'amount_local' => '-5000.0000'],
    ]);

    // Buy equipment: equipment 3000, cash -3000
    postBalancedEntry($company, [
        ['account_id' => $equipment->id, 'amount_local' => '3000.0000'],
        ['account_id' => $cash->id, 'amount_local' => '-3000.0000'],
    ]);

    // Earn revenue: cash 2000, revenue 2000
    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '2000.0000'],
        ['account_id' => $revenue->id, 'amount_local' => '-2000.0000'],
    ]);

    // Pay rent: expense 800, cash -800
    postBalancedEntry($company, [
        ['account_id' => $expense->id, 'amount_local' => '800.0000'],
        ['account_id' => $cash->id, 'amount_local' => '-800.0000'],
    ]);

    $service = app(BalanceSheetService::class);
    $result = $service->generate((int) $company->id, CarbonImmutable::now());

    expect($result['is_balanced'])->toBeTrue()
        ->and($result['total_assets'])->toBe('16200.0000')
        ->and($result['total_liabilities'])->toBe('5000.0000')
        ->and($result['total_equity'])->toBe('10000.0000')
        ->and($result['net_income'])->toBe('1200.0000');
});

it('income statement calculates net income correctly', function (): void {
    $company = createTestCompany();
    $cash = createAccount($company, '1000', 'Cash', AccountKind::Asset);
    $revenue = createAccount($company, '4000', 'Sales Revenue', AccountKind::Revenue);
    $consulting = createAccount($company, '4100', 'Consulting Revenue', AccountKind::Revenue);
    $expense = createAccount($company, '5000', 'Salary Expense', AccountKind::Expense);
    $rent = createAccount($company, '5100', 'Rent Expense', AccountKind::Expense);

    $period_start = CarbonImmutable::parse('2026-01-01');

    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '5000.0000'],
        ['account_id' => $revenue->id, 'amount_local' => '-5000.0000'],
    ], $period_start->addDays(5));

    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '3000.0000'],
        ['account_id' => $consulting->id, 'amount_local' => '-3000.0000'],
    ], $period_start->addDays(10));

    postBalancedEntry($company, [
        ['account_id' => $expense->id, 'amount_local' => '2000.0000'],
        ['account_id' => $cash->id, 'amount_local' => '-2000.0000'],
    ], $period_start->addDays(15));

    postBalancedEntry($company, [
        ['account_id' => $rent->id, 'amount_local' => '1500.0000'],
        ['account_id' => $cash->id, 'amount_local' => '-1500.0000'],
    ], $period_start->addDays(20));

    $service = new IncomeStatementService;
    $result = $service->generate(
        (int) $company->id,
        $period_start,
        $period_start->endOfMonth(),
    );

    expect($result['total_revenue'])->toBe('8000.0000')
        ->and($result['total_expenses'])->toBe('3500.0000')
        ->and($result['net_income'])->toBe('4500.0000')
        ->and($result['revenue'])->toHaveCount(2)
        ->and($result['expenses'])->toHaveCount(2);
});

it('income statement filters by date range', function (): void {
    $company = createTestCompany();
    $cash = createAccount($company, '1000', 'Cash', AccountKind::Asset);
    $revenue = createAccount($company, '4000', 'Sales Revenue', AccountKind::Revenue);
    $expense = createAccount($company, '5000', 'Salary Expense', AccountKind::Expense);

    // January entry
    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '1000.0000'],
        ['account_id' => $revenue->id, 'amount_local' => '-1000.0000'],
    ], CarbonImmutable::parse('2026-01-15'));

    // February entry
    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '2000.0000'],
        ['account_id' => $revenue->id, 'amount_local' => '-2000.0000'],
    ], CarbonImmutable::parse('2026-02-10'));

    // February expense
    postBalancedEntry($company, [
        ['account_id' => $expense->id, 'amount_local' => '500.0000'],
        ['account_id' => $cash->id, 'amount_local' => '-500.0000'],
    ], CarbonImmutable::parse('2026-02-20'));

    // March entry
    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '3000.0000'],
        ['account_id' => $revenue->id, 'amount_local' => '-3000.0000'],
    ], CarbonImmutable::parse('2026-03-05'));

    $service = new IncomeStatementService;
    $result = $service->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-02-01'),
        CarbonImmutable::parse('2026-02-28 23:59:59'),
    );

    expect($result['total_revenue'])->toBe('2000.0000')
        ->and($result['total_expenses'])->toBe('500.0000')
        ->and($result['net_income'])->toBe('1500.0000');
});

it('trial balance returns empty array for company with no entries', function (): void {
    $company = createTestCompany();
    createAccount($company, '1000', 'Cash', AccountKind::Asset);

    $service = new TrialBalanceService;
    $result = $service->generate((int) $company->id, CarbonImmutable::now());

    expect($result)->toBeArray()->toBeEmpty();
});

it('exports trial balance rows as csv', function (): void {
    $company = createTestCompany();
    $cash = createAccount($company, '1000', 'Cash, main bank', AccountKind::Asset);
    $revenue = createAccount($company, '4000', 'Sales Revenue', AccountKind::Revenue);

    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '1250.5000'],
        ['account_id' => $revenue->id, 'amount_local' => '-1250.5000'],
    ], CarbonImmutable::parse('2026-01-31 12:00:00'));

    $rows = app(TrialBalanceService::class)->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-01-31 23:59:59'),
    );

    $csv = app(FinancialReportCsvExporter::class)->trialBalance($rows);

    expect($csv)->toBe(implode("\n", [
        '"Account code","Account name","Account kind",Debit,Credit,Balance',
        '1000,"Cash, main bank",asset,1250.5000,0.0000,1250.5000',
        '4000,"Sales Revenue",revenue,0.0000,1250.5000,-1250.5000',
        '',
    ]));
});

it('exports income statement rows as csv', function (): void {
    $company = createTestCompany();
    $cash = createAccount($company, '1000', 'Cash', AccountKind::Asset);
    $revenue = createAccount($company, '4000', 'Sales, domestic', AccountKind::Revenue);
    $consulting = createAccount($company, '4100', 'Consulting Revenue', AccountKind::Revenue);
    $expense = createAccount($company, '5000', 'Salary Expense', AccountKind::Expense);

    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '2000.0000'],
        ['account_id' => $revenue->id, 'amount_local' => '-2000.0000'],
    ], CarbonImmutable::parse('2026-02-05 12:00:00'));
    postBalancedEntry($company, [
        ['account_id' => $cash->id, 'amount_local' => '750.2500'],
        ['account_id' => $consulting->id, 'amount_local' => '-750.2500'],
    ], CarbonImmutable::parse('2026-02-10 12:00:00'));
    postBalancedEntry($company, [
        ['account_id' => $expense->id, 'amount_local' => '500.0000'],
        ['account_id' => $cash->id, 'amount_local' => '-500.0000'],
    ], CarbonImmutable::parse('2026-02-20 12:00:00'));

    $report = app(IncomeStatementService::class)->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-02-01'),
        CarbonImmutable::parse('2026-02-28 23:59:59'),
    );

    $csv = app(FinancialReportCsvExporter::class)->incomeStatement($report);

    expect($csv)->toBe(implode("\n", [
        'Section,"Account code","Account name",Balance',
        'Revenue,4000,"Sales, domestic",2000.0000',
        'Revenue,4100,"Consulting Revenue",750.2500',
        'Expense,5000,"Salary Expense",500.0000',
        'Total,"Total revenue",,2750.2500',
        'Total,"Total expenses",,500.0000',
        'Total,"Net income",,2250.2500',
        '',
    ]));
});
