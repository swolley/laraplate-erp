<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

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
            $line = BankStatementLine::query()->lockForUpdate()->findOrFail($line->getKey());
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            $this->assertCanMatch($line, $payment);

            $line->matched_payment_id = $payment->getKey();
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
            $line = BankStatementLine::query()->lockForUpdate()->findOrFail($line->getKey());
            $line->matched_payment_id = null;
            $line->status = BankStatementLineStatus::Imported;
            $line->save();

            return $line;
        });
    }

    public function ignore(BankStatementLine $line): BankStatementLine
    {
        return DB::transaction(function () use ($line): BankStatementLine {
            $line = BankStatementLine::query()->lockForUpdate()->findOrFail($line->getKey());
            $line->matched_payment_id = null;
            $line->status = BankStatementLineStatus::Ignored;
            $line->save();

            return $line;
        });
    }

    private function assertCanMatch(BankStatementLine $line, Payment $payment): void
    {
        $line->loadMissing('bank_statement');

        if ((int) $line->company_id !== (int) $payment->company_id) {
            throw ValidationException::withMessages([
                'payment_id' => ['The payment belongs to a different company.'],
            ]);
        }

        if ($payment->bank_account_id !== null
            && (int) $payment->bank_account_id !== (int) $line->bank_statement?->bank_account_id) {
            throw ValidationException::withMessages([
                'payment_id' => ['The payment belongs to a different bank account.'],
            ]);
        }

        if ((string) $line->currency_doc !== (string) $payment->currency_doc) {
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
}
