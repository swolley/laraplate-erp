<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Currency;

use DateTimeInterface;
use Modules\ERP\Contracts\CurrencyConverter;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Support\ConnectionScopedTransaction;

final readonly class FxRevaluationService
{
    public function __construct(
        private CurrencyConverter $converter,
    ) {}

    public function revalueOpenSchedules(
        int $company_id,
        DateTimeInterface $as_of,
        int $balance_account_id,
        int $gain_account_id,
        int $loss_account_id,
    ): ?JournalEntry {
        $company = Company::query()->findOrFail($company_id);

        return ConnectionScopedTransaction::run($company, function () use ($company, $company_id, $as_of, $balance_account_id, $gain_account_id, $loss_account_id): ?JournalEntry {
            $local_currency = strtoupper((string) $company->default_currency);
            $lines = PaymentScheduleLine::query()
                ->with('invoice')
                ->where('company_id', $company_id)
                ->whereIn('status', [PaymentScheduleStatus::Open->value, PaymentScheduleStatus::Partial->value])
                ->where('currency_doc', '!=', $local_currency)
                ->lockForUpdate()
                ->get();

            $delta = 0.0;

            foreach ($lines as $line) {
                $open_doc = max(0.0, (float) $line->amount_doc - (float) $line->paid_amount_doc);
                $open_local = max(0.0, (float) $line->amount_local - (float) $line->paid_amount_local);
                $converted = $this->converter->convert($line->currency_doc, $local_currency, $open_doc, $as_of);
                $line_delta = round($converted['amount'] - $open_local, 4);

                if ($line->invoice?->direction === InvoiceDirection::Purchase) {
                    $line_delta *= -1;
                }

                $delta += $line_delta;
            }

            $delta = round($delta, 4);

            if (abs($delta) < 0.0001) {
                return null;
            }

            $entry = JournalEntry::query()->create([
                'company_id' => $company_id,
                'posted_at' => $as_of,
                'reference_type' => 'fx_revaluation',
                'description' => 'FX revaluation at ' . $as_of->format('Y-m-d'),
            ]);

            if ($delta > 0) {
                $this->line($entry, 1, $balance_account_id, $delta, $local_currency, 'FX revaluation balance increase');
                $this->line($entry, 2, $gain_account_id, -$delta, $local_currency, 'Unrealized FX gain');
            } else {
                $amount = abs($delta);
                $this->line($entry, 1, $loss_account_id, $amount, $local_currency, 'Unrealized FX loss');
                $this->line($entry, 2, $balance_account_id, -$amount, $local_currency, 'FX revaluation balance decrease');
            }

            return $entry->load('lines');
        });
    }

    private function line(JournalEntry $entry, int $line_no, int $account_id, float $amount, string $currency, string $description): void
    {
        $entry->lines()->create([
            'line_no' => $line_no,
            'account_id' => $account_id,
            'amount_doc' => number_format($amount, 4, '.', ''),
            'currency_doc' => $currency,
            'amount_local' => number_format($amount, 4, '.', ''),
            'currency_local' => $currency,
            'fx_rate' => '1.00000000',
            'description' => $description,
        ]);
    }
}
