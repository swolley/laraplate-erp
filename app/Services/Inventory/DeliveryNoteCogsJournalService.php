<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Services\Accounting\ChartOfAccountsInstaller;
use Modules\ERP\Services\Accounting\JournalPostingService;

/**
 * Posts a balanced COGS / inventory relief journal when a {@see DeliveryNote}
 * inventory posting creates outbound {@see StockMovement} rows with unit costs.
 */
final class DeliveryNoteCogsJournalService
{
    public const string META_ROLE_INVENTORY_MERCHANDISE = 'inventory_merchandise';

    public const string META_ROLE_COST_OF_GOODS_SOLD = 'cost_of_goods_sold';

    public function __construct(
        private readonly ChartOfAccountsInstaller $chart_of_accounts_installer,
        private readonly JournalPostingService $journal_posting_service,
    ) {}

    /**
     * Mutates {@see DeliveryNote::$cogs_journal_entry_id} on the same model instance
     * passed from {@see DeliveryNoteInventoryService} so the saving cycle persists it.
     *
     * @param  Collection<int, DeliveryNoteLine>  $delivery_note_lines
     */
    public function postForDeliveryNoteIfNeeded(DeliveryNote $delivery_note, Collection $delivery_note_lines): void
    {
        if ($delivery_note->cogs_journal_entry_id !== null) {
            return;
        }

        if ($delivery_note_lines->isEmpty()) {
            return;
        }

        $company = Company::query()->withoutGlobalScopes()->whereKey((int) $delivery_note->company_id)->firstOrFail();

        $this->chart_of_accounts_installer->installWhenEmpty($company);

        $cogs_account = $this->findAccountByMetaRole($company, self::META_ROLE_COST_OF_GOODS_SOLD);
        $inventory_account = $this->findAccountByMetaRole($company, self::META_ROLE_INVENTORY_MERCHANDISE);

        $line_ids = $delivery_note_lines->pluck('id')->all();

        $movements = StockMovement::query()
            ->where('company_id', $company->id)
            ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
            ->whereIn('source_id', $line_ids)
            ->where('direction', StockMovementDirection::OUT)
            ->get();

        if ($movements->isEmpty()) {
            throw ValidationException::withMessages([
                'stock' => ['Expected outbound stock movements for this delivery note were not found.'],
            ]);
        }

        $total_cost_local = '0.0000';

        foreach ($movements as $movement) {
            if ($movement->unit_cost === null) {
                throw ValidationException::withMessages([
                    'unit_cost' => ['Outbound stock movement is missing unit_cost; cannot post COGS.'],
                ]);
            }

            $line_total = $this->multiplyMoney((string) $movement->unit_cost, (string) $movement->quantity);
            $total_cost_local = $this->addMoney($total_cost_local, $line_total);
        }

        if ($this->absMoney($total_cost_local) < 0.0000001) {
            return;
        }

        $currency = (string) $company->default_currency;
        $fx_rate = '1';

        $journal_lines = [
            [
                'account_id' => (int) $cogs_account->getKey(),
                'amount_doc' => $total_cost_local,
                'currency_doc' => $currency,
                'amount_local' => $total_cost_local,
                'currency_local' => $currency,
                'fx_rate' => $fx_rate,
                'description' => 'COGS',
            ],
            [
                'account_id' => (int) $inventory_account->getKey(),
                'amount_doc' => $this->negateMoney($total_cost_local),
                'currency_doc' => $currency,
                'amount_local' => $this->negateMoney($total_cost_local),
                'currency_local' => $currency,
                'fx_rate' => $fx_rate,
                'description' => 'Inventory relief',
            ],
        ];

        $reference_label = $delivery_note->reference !== null && $delivery_note->reference !== ''
            ? (string) $delivery_note->reference
            : '#'.$delivery_note->getKey();
        $description = 'COGS — Delivery note '.$reference_label;

        $entry = $this->journal_posting_service->post(
            $company,
            $journal_lines,
            null,
            $description,
            null,
            $delivery_note,
        );

        $delivery_note->cogs_journal_entry_id = (int) $entry->getKey();
    }

    public function reverseForDeliveryNoteIfNeeded(DeliveryNote $delivery_note): void
    {
        if ($delivery_note->cogs_journal_entry_id === null) {
            return;
        }

        $company = Company::query()->withoutGlobalScopes()->whereKey((int) $delivery_note->company_id)->firstOrFail();
        $entry = JournalEntry::query()
            ->withoutGlobalScopes()
            ->whereKey((int) $delivery_note->cogs_journal_entry_id)
            ->first();

        if ($entry === null) {
            $delivery_note->cogs_journal_entry_id = null;

            return;
        }

        $reference_label = $delivery_note->reference !== null && $delivery_note->reference !== ''
            ? (string) $delivery_note->reference
            : '#'.$delivery_note->getKey();
        $reason = 'Delivery note unposted: '.$reference_label;

        $this->journal_posting_service->reverse($entry, $company, $reason);
        $delivery_note->cogs_journal_entry_id = null;
    }

    private function findAccountByMetaRole(Company $company, string $role): Account
    {
        /** @var Account|null $account */
        $account = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('meta->erp_role', $role)
            ->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'accounts' => ['Chart of accounts is missing account meta erp_role='.$role.'.'],
            ]);
        }

        return $account;
    }

    private function formatMoney4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }

    private function multiplyMoney(string $unit_cost, string $quantity): string
    {
        return $this->formatMoney4((float) $unit_cost * (float) $quantity);
    }

    private function addMoney(string $a, string $b): string
    {
        return $this->formatMoney4((float) $a + (float) $b);
    }

    private function negateMoney(string $value): string
    {
        return $this->formatMoney4(-1.0 * (float) $value);
    }

    private function absMoney(string $value): float
    {
        return abs((float) $value);
    }
}
