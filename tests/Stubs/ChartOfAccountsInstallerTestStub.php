<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs;

use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Contracts\ChartOfAccountsProvider;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Accounting\ChartOfAccountsInstaller;

/**
 * Exposes a deterministic sort order for installer edge-case tests.
 */
final class ChartOfAccountsInstallerTestStub extends ChartOfAccountsInstaller
{
    /**
     * @param  list<array{code: string, name: string, kind: AccountKind, parent_code: string|null, meta?: array<string, mixed>}>  $forced_sort
     */
    public function __construct(
        ChartOfAccountsProvider $provider,
        private readonly array $forced_sort,
    ) {
        parent::__construct($provider);
    }

    public function installWithForcedSort(Company $company): void
    {
        $company_id = is_int($company->id) ? $company->id : (int) $company->id;

        if (Account::query()->withoutGlobalScopes()->where('company_id', $company_id)->exists()) {
            return;
        }

        $definitions = $this->forced_sort;
        $id_by_code = [];

        foreach ($definitions as $row) {
            $parent_id = null;

            if ($row['parent_code'] !== null) {
                $parent_id = $id_by_code[$row['parent_code']] ?? null;

                if ($parent_id === null) {
                    throw new \InvalidArgumentException(
                        'Parent code "' . $row['parent_code'] . '" not found for account "' . $row['code'] . '".',
                    );
                }
            }

            $account = new Account([
                'company_id' => $company_id,
                'code' => $row['code'],
                'name' => $row['name'],
                'kind' => $row['kind'],
                'parent_id' => $parent_id,
                'meta' => $row['meta'] ?? null,
                'is_active' => true,
            ]);
            $account->setSkipValidation(true);
            $account->save();

            $id_by_code[$row['code']] = is_int($account->id) ? $account->id : (int) $account->id;
        }
    }
}
