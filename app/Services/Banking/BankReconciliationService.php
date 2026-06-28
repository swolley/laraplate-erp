<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Payment;

final class BankReconciliationService
{
    public function matchPayment(BankStatementLine $line, Payment $payment): BankStatementLine
    {
        $this->assertCanMatch($line, $payment);

        return DB::transaction(function () use ($line, $payment): BankStatementLine {
            $line = BankStatementLine::query()->lockForUpdate()->whereKey($line->id)->firstOrFail();
            $payment = Payment::query()->lockForUpdate()->whereKey($payment->id)->firstOrFail();

            $this->assertCanMatch($line, $payment);

            $line->matched_payment_id = $this->paymentId($payment);
            $line->status = BankStatementLineStatus::Matched;
            $line->save();

            if ($payment->bank_account_id === null) {
                $payment->bank_account_id = $line->bank_statement?->bank_account_id;
                $payment->save();
            }

            return $line;
        });
    }

    public function unmatch(BankStatementLine $line): BankStatementLine
    {
        return DB::transaction(function () use ($line): BankStatementLine {
            $line = BankStatementLine::query()->lockForUpdate()->whereKey($line->id)->firstOrFail();
            $line->matched_payment_id = null;
            $line->status = BankStatementLineStatus::Imported;
            $line->save();

            return $line;
        });
    }

    public function ignore(BankStatementLine $line): BankStatementLine
    {
        return DB::transaction(function () use ($line): BankStatementLine {
            $line = BankStatementLine::query()->lockForUpdate()->whereKey($line->id)->firstOrFail();
            $line->matched_payment_id = null;
            $line->status = BankStatementLineStatus::Ignored;
            $line->save();

            return $line;
        });
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

        $query = Payment::query()
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
        $payment_amount = (float) $payment->amount_doc;

        if (($line_amount <=> 0.0) !== $expected_sign || abs(abs($line_amount) - $payment_amount) > 0.0001) {
            throw ValidationException::withMessages([
                'amount_doc' => ['The payment amount does not match the bank statement line.'],
            ]);
        }
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
}
