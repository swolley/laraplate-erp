<?php

declare(strict_types=1);

namespace Modules\Business\Services\Accounting;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Business\Contracts\ChartOfAccountsProvider;
use Modules\Business\Models\Account;
use Modules\Business\Models\Company;

/**
 * Idempotent loader that materialises a {@see ChartOfAccountsProvider} definition set
 * under a {@see Company}.
 */
final class ChartOfAccountsInstaller
{
    public function __construct(private readonly ChartOfAccountsProvider $provider) {}

    /**
     * Insert all accounts for the company when the chart is still empty.
     */
    public function installWhenEmpty(Company $company): void
    {
        $company_id = (int) $company->getKey();

        if (Account::query()->withoutGlobalScopes()->where('company_id', $company_id)->exists()) {
            return;
        }

        $definitions = $this->topologicallySortedDefinitions($this->provider->definitions());

        DB::transaction(function () use ($company_id, $definitions): void {
            /** @var array<string, int> $id_by_code */
            $id_by_code = [];

            foreach ($definitions as $row) {
                $parent_id = null;

                if ($row['parent_code'] !== null) {
                    $parent_id = $id_by_code[$row['parent_code']] ?? null;

                    if ($parent_id === null) {
                        throw new InvalidArgumentException(
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

                $id_by_code[$row['code']] = (int) $account->getKey();
            }
        });
    }

    /**
     * @param  list<array{code: string, name: string, kind: \Modules\Business\Casts\AccountKind, parent_code: string|null, meta?: array<string, mixed>}>  $definitions
     * @return list<array{code: string, name: string, kind: \Modules\Business\Casts\AccountKind, parent_code: string|null, meta?: array<string, mixed>}>
     */
    private function topologicallySortedDefinitions(array $definitions): array
    {
        /** @var array<string, array{code: string, name: string, kind: \Modules\Business\Casts\AccountKind, parent_code: string|null, meta?: array<string, mixed>}> $by_code */
        $by_code = [];

        foreach ($definitions as $row) {
            if (isset($by_code[$row['code']])) {
                throw new InvalidArgumentException('Duplicate account code in chart definitions: ' . $row['code']);
            }

            $by_code[$row['code']] = $row;
        }

        $sorted = [];
        $queue = [];

        foreach ($definitions as $row) {
            if ($row['parent_code'] === null) {
                $queue[] = $row['code'];
            }
        }

        if ($queue === []) {
            throw new InvalidArgumentException('Chart definitions must contain at least one root account (parent_code null).');
        }

        while ($queue !== []) {
            /** @var string $code */
            $code = array_shift($queue);
            $sorted[] = $by_code[$code];

            foreach ($definitions as $child) {
                if ($child['parent_code'] === $code) {
                    $queue[] = $child['code'];
                }
            }
        }

        if (count($sorted) !== count($definitions)) {
            throw new InvalidArgumentException('Chart definitions contain a cycle or a missing parent_code reference.');
        }

        return $sorted;
    }
}
