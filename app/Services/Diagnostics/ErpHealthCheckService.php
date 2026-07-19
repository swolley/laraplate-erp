<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Diagnostics;

use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Permission;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Throwable;

final class ErpHealthCheckService
{
    /**
     * @return array{checks: list<array{key: string, status: 'success'|'warning'|'failure', message: string}>, summary: array{success: int, warning: int, failure: int}}
     */
    public function run(): array
    {
        $checks = [];
        $company = $this->defaultCompany($checks);

        if ($company instanceof Company) {
            $this->checkCompanyAccounting($company, $checks);
            $this->checkDocumentSequences($company, $checks);
        }

        $this->checkPermissions($checks);
        $this->checkEInvoiceConfiguration($checks);

        return [
            'checks' => $checks,
            'summary' => [
                'success' => $this->countStatus($checks, 'success'),
                'warning' => $this->countStatus($checks, 'warning'),
                'failure' => $this->countStatus($checks, 'failure'),
            ],
        ];
    }

    /**
     * @param  list<array{key: string, status: 'success'|'warning'|'failure', message: string}>  $checks
     */
    private function defaultCompany(array &$checks): ?Company
    {
        if (! Schema::hasTable(ERPTables::Companies->value)) {
            $this->add($checks, 'default_company', 'failure', 'ERP companies table is missing. Run ERP migrations.');

            return null;
        }

        return $this->guarded($checks, 'default_company', function () use (&$checks): ?Company {
            $company = Company::getDefault();

            if (! $company instanceof Company) {
                $this->add($checks, 'default_company', 'failure', 'No default ERP company is configured.');

                return null;
            }

            $this->add($checks, 'default_company', 'success', sprintf('Default company [%s] is configured.', $company->name));

            return $company;
        });
    }

    /**
     * @param  list<array{key: string, status: 'success'|'warning'|'failure', message: string}>  $checks
     */
    private function checkCompanyAccounting(Company $company, array &$checks): void
    {
        $company_id = (int) $company->getKey();

        $this->checkTableCount(
            $checks,
            'chart_of_accounts',
            ERPTables::Accounts,
            fn (): int => Account::query()->withoutGlobalScopes()->where('company_id', $company_id)->count(),
            'Chart of accounts is available.',
            'The default company has no chart of accounts.',
        );

        if (! Schema::hasTable(ERPTables::FiscalYears->value) || ! Schema::hasTable(ERPTables::FiscalPeriods->value)) {
            $this->add($checks, 'fiscal_calendar', 'failure', 'Fiscal year or fiscal period table is missing.');

            return;
        }

        $this->guarded($checks, 'fiscal_calendar', function () use ($company_id, &$checks): void {
            $today = now()->toDateString();
            $fiscal_year = FiscalYear::query()->withoutGlobalScopes()
                ->where('company_id', $company_id)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->first();

            if (! $fiscal_year instanceof FiscalYear) {
                $this->add($checks, 'fiscal_calendar', 'failure', 'No fiscal year covers the current date.');

                return;
            }

            $period_count = FiscalPeriod::query()->withoutGlobalScopes()
                ->where('fiscal_year_id', $fiscal_year->getKey())
                ->count();

            if ($period_count === 0) {
                $this->add($checks, 'fiscal_calendar', 'failure', sprintf('Fiscal year %d has no periods.', $fiscal_year->year));

                return;
            }

            $this->add($checks, 'fiscal_calendar', 'success', sprintf('Fiscal year %d has %d periods.', $fiscal_year->year, $period_count));
        });
    }

    /**
     * @param  list<array{key: string, status: 'success'|'warning'|'failure', message: string}>  $checks
     */
    private function checkDocumentSequences(Company $company, array &$checks): void
    {
        $this->checkTableCount(
            $checks,
            'document_sequences',
            ERPTables::DocumentSequences,
            fn (): int => DocumentSequence::query()->withoutGlobalScopes()
                ->where('company_id', (int) $company->getKey())
                ->where('fiscal_year', now()->year)
                ->count(),
            'At least one document sequence exists for the current year.',
            'No document sequence exists for the current year.',
        );
    }

    /**
     * @param  list<array{key: string, status: 'success'|'warning'|'failure', message: string}>  $checks
     */
    private function checkPermissions(array &$checks): void
    {
        $permission = new Permission();

        if (! Schema::hasTable($permission->getTable())) {
            $this->add($checks, 'domain_permissions', 'failure', 'Core permissions table is missing.');

            return;
        }

        $this->guarded($checks, 'domain_permissions', function () use (&$checks): void {
            $required = $this->requiredPermissionNames();
            $present = Permission::query()->whereIn('name', $required)->pluck('name')->all();
            $missing = array_values(array_diff($required, $present));

            if ($missing !== []) {
                $this->add($checks, 'domain_permissions', 'failure', 'Missing ERP domain permissions: ' . implode(', ', $missing));

                return;
            }

            $this->add($checks, 'domain_permissions', 'success', sprintf('%d required ERP domain permissions are available.', count($required)));
        });
    }

    /**
     * @param  list<array{key: string, status: 'success'|'warning'|'failure', message: string}>  $checks
     */
    private function checkEInvoiceConfiguration(array &$checks): void
    {
        $driver = (string) config('erp.einvoice.driver', 'stub');

        if (! in_array($driver, ['stub', 'fatturapa', 'aruba'], true)) {
            $this->add($checks, 'einvoice', 'failure', sprintf('Unsupported e-invoice driver [%s].', $driver));

            return;
        }

        if ($driver !== 'aruba') {
            $this->add($checks, 'einvoice', 'success', sprintf('E-invoice driver [%s] is internally valid.', $driver));

            return;
        }

        $base_url = mb_trim((string) config('erp.einvoice.aruba.base_url'));
        $token = mb_trim((string) config('erp.einvoice.aruba.token'));
        $username = mb_trim((string) config('erp.einvoice.aruba.username'));
        $password = mb_trim((string) config('erp.einvoice.aruba.password'));

        if ($base_url === '' || ($token === '' && ($username === '' || $password === ''))) {
            $this->add($checks, 'einvoice', 'failure', 'Aruba requires a base URL and either a token or username/password credentials.');

            return;
        }

        $this->add($checks, 'einvoice', 'success', 'Aruba endpoint and authentication configuration are present.');

        if (mb_trim((string) config('erp.einvoice.aruba.callback_api_key')) === '') {
            $this->add($checks, 'einvoice_callback', 'warning', 'Aruba callback API key is missing; polling remains available.');
        }
    }

    /**
     * @return list<string>
     */
    private function requiredPermissionNames(): array
    {
        return [
            'default.' . ERPTables::DeliveryNotes->value . '.post',
            'default.' . ERPTables::DeliveryNotes->value . '.unpost',
            'default.' . ERPTables::DocumentSequences->value . '.reserve',
            'default.' . ERPTables::DocumentSequences->value . '.reset',
            'default.' . ERPTables::FiscalPeriods->value . '.close',
            'default.' . ERPTables::FiscalPeriods->value . '.reopen',
            'default.' . ERPTables::FiscalYears->value . '.close',
            'default.' . ERPTables::Invoices->value . '.post',
            'default.' . ERPTables::Invoices->value . '.unpost',
            'default.' . ERPTables::Invoices->value . '.submitEInvoice',
            'default.' . ERPTables::Invoices->value . '.refreshEInvoice',
            'default.' . ERPTables::JournalEntries->value . '.post',
            'default.' . ERPTables::JournalEntries->value . '.unpost',
            'default.' . ERPTables::JournalEntries->value . '.reverse',
            'default.' . ERPTables::Companies->value . '.switch_context',
            'default.' . ERPTables::TaxCodes->value . '.supersede',
        ];
    }

    /**
     * @param  list<array{key: string, status: 'success'|'warning'|'failure', message: string}>  $checks
     * @param  callable(): int  $count
     */
    private function checkTableCount(array &$checks, string $key, ERPTables $table, callable $count, string $success, string $failure): void
    {
        if (! Schema::hasTable($table->value)) {
            $this->add($checks, $key, 'failure', sprintf('Table [%s] is missing.', $table->value));

            return;
        }

        $this->guarded($checks, $key, function () use (&$checks, $key, $count, $success, $failure): void {
            $rows = $count();

            $this->add($checks, $key, $rows > 0 ? 'success' : 'failure', $rows > 0 ? $success : $failure);
        });
    }

    /**
     * @param  list<array{key: string, status: 'success'|'warning'|'failure', message: string}>  $checks
     */
    private function guarded(array &$checks, string $key, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            $this->add($checks, $key, 'failure', $exception->getMessage());

            return null;
        }
    }

    /**
     * @param  list<array{key: string, status: 'success'|'warning'|'failure', message: string}>  $checks
     */
    private function add(array &$checks, string $key, string $status, string $message): void
    {
        $checks[] = ['key' => $key, 'status' => $status, 'message' => $message];
    }

    /**
     * @param  list<array{key: string, status: 'success'|'warning'|'failure', message: string}>  $checks
     */
    private function countStatus(array $checks, string $status): int
    {
        return count(array_filter($checks, static fn (array $check): bool => $check['status'] === $status));
    }
}
