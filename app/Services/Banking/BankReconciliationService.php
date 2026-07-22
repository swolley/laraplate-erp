<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Payment;
use Modules\Core\Services\OutboxRecorder;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Support\ConnectionScopedModels;

final class BankReconciliationService
{
    public function __construct(
        private readonly BankDifferenceJournalService $bank_difference_journal_service,
        private readonly OutboxRecorder $outbox_recorder,
    ) {}

    public function matchPayment(BankStatementLine $line, Payment $payment): BankStatementLine
    {
        $this->assertCanMatch($line, $payment);

        return ConnectionScopedTransaction::run($line, function (ConnectionScopedModels $models) use ($line, $payment): BankStatementLine {
            $line = $models->query(BankStatementLine::class)->lockForUpdate()->whereKey($line->id)->firstOrFail();
            $payment = $models->query(Payment::class)->lockForUpdate()->whereKey($payment->id)->firstOrFail();

            $this->assertCanMatch($line, $payment);

            $line->matched_payment_id = $this->paymentId($payment);
            $line->status = BankStatementLineStatus::Matched;
            $line->save();

            if ($payment->bank_account_id === null) {
                $payment->bank_account_id = $line->bank_statement?->bank_account_id;
                $payment->save();
            }

            $this->recordPaymentMatched($line, $payment);

            return $line;
        }, $payment);
    }

    public function unmatch(BankStatementLine $line): BankStatementLine
    {
        return ConnectionScopedTransaction::run($line, function (ConnectionScopedModels $models) use ($line): BankStatementLine {
            $line = $models->query(BankStatementLine::class)->lockForUpdate()->whereKey($line->id)->firstOrFail();

            if ($line->difference_journal_entry_id !== null) {
                throw ValidationException::withMessages([
                    'difference_journal_entry_id' => ['Bank statement lines matched with a difference cannot be unmatched without reversing the difference journal.'],
                ]);
            }

            $line->matched_payment_id = null;
            $line->status = BankStatementLineStatus::Imported;
            $line->save();

            return $line;
        });
    }

    public function ignore(BankStatementLine $line): BankStatementLine
    {
        return ConnectionScopedTransaction::run($line, function (ConnectionScopedModels $models) use ($line): BankStatementLine {
            $line = $models->query(BankStatementLine::class)->lockForUpdate()->whereKey($line->id)->firstOrFail();
            $line->matched_payment_id = null;
            $line->status = BankStatementLineStatus::Ignored;
            $line->save();

            return $line;
        });
    }

    public function matchPaymentWithDifference(
        BankStatementLine $line,
        Payment $payment,
        int $expense_account_id,
    ): BankStatementLine {
        return ConnectionScopedTransaction::run($line, function (ConnectionScopedModels $models) use ($line, $payment, $expense_account_id): BankStatementLine {
            $line = $models->query(BankStatementLine::class)
                ->with('bank_statement')
                ->lockForUpdate()
                ->whereKey($line->id)
                ->firstOrFail();
            $payment = $models->query(Payment::class)->lockForUpdate()->whereKey($payment->id)->firstOrFail();

            $this->assertCanMatchContext($line, $payment);

            $difference_amount_doc = $this->differenceAmount($line, $payment);

            if (abs((float) $difference_amount_doc) <= 0.00005) {
                throw ValidationException::withMessages([
                    'amount_doc' => ['Use exact match when there is no bank reconciliation difference.'],
                ]);
            }

            $company = $models->query(Company::class)->withoutGlobalScopes()->findOrFail($line->company_id);
            $bank_account_id = $line->bank_statement?->bank_account_id;

            if ($bank_account_id === null) {
                throw ValidationException::withMessages([
                    'bank_statement_id' => ['The bank statement line is missing its bank account.'],
                ]);
            }

            $difference_journal_entry = $this->bank_difference_journal_service->postDifference(
                $company,
                $line,
                $payment,
                $difference_amount_doc,
                $expense_account_id,
                (int) $bank_account_id,
            );

            $line->matched_payment_id = $this->paymentId($payment);
            $line->difference_journal_entry_id = $this->journalEntryId($difference_journal_entry);
            $line->status = BankStatementLineStatus::Matched;
            $line->save();

            if ($payment->bank_account_id === null) {
                $payment->bank_account_id = $bank_account_id;
                $payment->save();
            }

            $this->recordPaymentMatched($line, $payment);

            return $line;
        }, $payment);
    }

    private function recordPaymentMatched(BankStatementLine $line, Payment $payment): void
    {
        $this->outbox_recorder->record('erp.payment.matched', $line, [
            'company_id' => (int) $line->company_id,
            'payment_id' => $this->paymentId($payment),
            'difference_journal_entry_id' => $line->difference_journal_entry_id === null
                ? null
                : (int) $line->difference_journal_entry_id,
        ]);
    }

    /**
     * @return Collection<int, Payment>
     */
    public function suggestPayments(BankStatementLine $line): Collection
    {
        $line->loadMissing('bank_statement');

        $line_amount = (float) $line->amount_doc;
        $direction = $line_amount >= 0 ? PaymentDirection::Inbound : PaymentDirection::Outbound;
        $expected_amount = abs($line_amount);
        $bank_statement = $line->bank_statement;
        $bank_account_id = $bank_statement?->bank_account_id;
        $booked_at = $line->booked_at;

        $query = ConnectionScopedModels::for($line)->query(Payment::class)
            ->with('party')
            ->where('company_id', $line->company_id)
            ->where('currency_doc', $line->currency_doc)
            ->where('direction', $direction->value)
            ->whereBetween('amount_doc', [
                number_format(max(0, $expected_amount - 1), 4, '.', ''),
                number_format($expected_amount + 1, 4, '.', ''),
            ]);

        if ($bank_account_id !== null) {
            $query->where(static function (Builder $inner_query) use ($bank_account_id): void {
                $inner_query->whereNull('bank_account_id')
                    ->orWhere('bank_account_id', $bank_account_id);
            });
        }

        if ($booked_at !== null) {
            $query->whereBetween('payment_date', [
                $booked_at->copy()->subDays(5)->format('Y-m-d'),
                $booked_at->copy()->addDays(5)->format('Y-m-d'),
            ]);
        }

        return $query
            ->limit(50)
            ->get()
            ->sortByDesc(fn (Payment $payment): int => $this->suggestionScore($line, $payment))
            ->values();
    }

    private function assertCanMatch(BankStatementLine $line, Payment $payment): void
    {
        $this->assertCanMatchContext($line, $payment);

        $difference_amount_doc = $this->differenceAmount($line, $payment);

        if (abs((float) $difference_amount_doc) > 0.0001) {
            throw ValidationException::withMessages([
                'amount_doc' => ['The payment amount does not match the bank statement line.'],
            ]);
        }
    }

    private function assertCanMatchContext(BankStatementLine $line, Payment $payment): void
    {
        $line->loadMissing('bank_statement');

        $statement_bank_account_id = $line->bank_statement?->bank_account_id;

        if ($line->company_id !== $payment->company_id) {
            throw ValidationException::withMessages([
                'payment_id' => ['The payment belongs to a different company.'],
            ]);
        }

        if ($payment->bank_account_id !== null
            && $payment->bank_account_id !== $statement_bank_account_id) {
            throw ValidationException::withMessages([
                'payment_id' => ['The payment belongs to a different bank account.'],
            ]);
        }

        if ($line->currency_doc !== $payment->currency_doc) {
            throw ValidationException::withMessages([
                'currency_doc' => ['The payment currency does not match the bank statement line.'],
            ]);
        }

        $expected_sign = $payment->direction === PaymentDirection::Inbound ? 1 : -1;
        $line_amount = (float) $line->amount_doc;

        if (($line_amount <=> 0.0) !== $expected_sign) {
            throw ValidationException::withMessages([
                'amount_doc' => ['The payment amount does not match the bank statement line.'],
            ]);
        }
    }

    /**
     * Signed difference aligned with the bank statement direction.
     *
     * @return numeric-string
     */
    private function differenceAmount(BankStatementLine $line, Payment $payment): string
    {
        $expected_sign = $payment->direction === PaymentDirection::Inbound ? 1 : -1;
        $line_amount = (float) $line->amount_doc;
        $expected_line_amount = $expected_sign * (float) $payment->amount_doc;

        return number_format(round($line_amount - $expected_line_amount, 4), 4, '.', '');
    }

    private function suggestionScore(BankStatementLine $line, Payment $payment): int
    {
        $score = 0;

        if ((float) $line->amount_doc !== 0.0 && abs(abs((float) $line->amount_doc) - (float) $payment->amount_doc) < 0.0001) {
            $score += 50;
        }

        if ($line->booked_at !== null && $payment->payment_date !== null) {
            $score += max(0, 20 - (int) abs($line->booked_at->diffInDays($payment->payment_date)));
        }

        if ($line->reference !== null && $payment->reference !== null
            && mb_strtolower($line->reference) === mb_strtolower($payment->reference)) {
            $score += 30;
        }

        if ($payment->bank_account_id !== null) {
            $score += 10;
        }

        return $score;
    }

    private function paymentId(Payment $payment): int
    {
        return is_int($payment->id) ? $payment->id : (int) $payment->id;
    }

    private function journalEntryId(\Modules\ERP\Models\JournalEntry $journal_entry): int
    {
        return is_int($journal_entry->id) ? $journal_entry->id : (int) $journal_entry->id;
    }
}
