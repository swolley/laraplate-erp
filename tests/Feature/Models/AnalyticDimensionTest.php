<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\AnalyticDimension;
use Modules\ERP\Models\AnalyticDimensionValue;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\JournalEntry;

uses(RefreshDatabase::class);

it('assigns analytic dimension values to journal entry lines through a pivot model', function (): void {
    $company = Company::query()->create([
        'slug' => 'analytic-' . uniqid(),
        'name' => 'Analytic Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $account = Account::query()->create([
        'company_id' => $company->id,
        'code' => '6000',
        'name' => 'Services',
        'kind' => AccountKind::Expense,
        'is_active' => true,
    ]);
    $dimension = AnalyticDimension::query()->create([
        'company_id' => $company->id,
        'code' => 'cost_center',
        'name' => 'Cost center',
        'is_active' => true,
    ]);
    $value = AnalyticDimensionValue::query()->create([
        'company_id' => $company->id,
        'analytic_dimension_id' => $dimension->id,
        'code' => 'sales',
        'name' => 'Sales',
        'is_active' => true,
    ]);
    $entry = JournalEntry::query()->create([
        'company_id' => $company->id,
        'description' => 'Analytic allocation',
    ]);
    $line = $entry->lines()->create([
        'line_no' => 1,
        'account_id' => $account->id,
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);

    $line->analytic_dimension_values()->attach($value->id, ['allocation_percent' => '100.0000']);
    $line->load('analytic_dimension_values.dimension');

    expect($dimension->refresh()->code)->toBe('COST_CENTER')
        ->and($value->refresh()->code)->toBe('SALES')
        ->and($line->analytic_dimension_values)->toHaveCount(1)
        ->and($line->analytic_dimension_values->first()->pivot->allocation_percent)->toBe('100.0000')
        ->and($line->analytic_dimension_values->first()->dimension->code)->toBe('COST_CENTER');
});
