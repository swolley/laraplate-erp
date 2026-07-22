<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\PaymentRunFormat;
use Modules\ERP\Casts\PaymentRunLineStatus;
use Modules\ERP\Casts\PaymentRunStatus;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\PartyBankAccount;
use Modules\ERP\Models\PaymentRun;
use Modules\ERP\Models\PaymentRunLine;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Support\ConnectionScopedTransaction;

final class PaymentRunBuilderService
{
    /**
     * @param  list<int>  $payment_schedule_line_ids
     */
    public function build(
        int $company_id,
        int $bank_account_id,
        array $payment_schedule_line_ids,
        CarbonInterface|string $execution_date,
    ): PaymentRun {
        if ($payment_schedule_line_ids === []) {
            throw ValidationException::withMessages([
                'payment_schedule_line_ids' => ['At least one payment schedule line is required.'],
            ]);
        }

        $bank_account = BankAccount::query()
            ->whereKey($bank_account_id)
            ->where('company_id', $company_id)
            ->firstOrFail();

        return ConnectionScopedTransaction::run($bank_account, function () use ($company_id, $bank_account_id, $payment_schedule_line_ids, $execution_date): PaymentRun {
            $bank_account = BankAccount::query()
                ->whereKey($bank_account_id)
                ->where('company_id', $company_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bank_account->iban === null || trim((string) $bank_account->iban) === '') {
                throw ValidationException::withMessages([
                    'bank_account_id' => ['The company bank account must have an IBAN before SEPA export.'],
                ]);
            }

            $schedule_lines = PaymentScheduleLine::query()
                ->with(['invoice.party'])
                ->whereIn('id', $payment_schedule_line_ids)
                ->where('company_id', $company_id)
                ->lockForUpdate()
                ->get();

            if ($schedule_lines->count() !== count(array_unique($payment_schedule_line_ids))) {
                throw ValidationException::withMessages([
                    'payment_schedule_line_ids' => ['One or more payment schedule lines do not belong to the company.'],
                ]);
            }

            $execution = $execution_date instanceof CarbonInterface
                ? CarbonImmutable::instance($execution_date)
                : CarbonImmutable::parse($execution_date);

            $run = PaymentRun::query()->create([
                'company_id' => $company_id,
                'bank_account_id' => $bank_account_id,
                'execution_date' => $execution->toDateString(),
                'currency' => strtoupper((string) $bank_account->currency),
                'total_amount_doc' => '0.0000',
                'total_amount_local' => '0.0000',
                'status' => PaymentRunStatus::Draft,
                'format' => PaymentRunFormat::SepaPain001,
            ]);

            $total_doc = 0.0;
            $total_local = 0.0;

            foreach ($schedule_lines as $schedule_line) {
                $residual_doc = $this->residualAmount($schedule_line);
                $residual_local = $this->round4((float) $schedule_line->amount_local - (float) $schedule_line->paid_amount_local);

                if ((float) $residual_doc <= 0.00005 || (float) $residual_local <= 0.00005) {
                    throw ValidationException::withMessages([
                        'payment_schedule_line_ids' => ['Paid payment schedule lines cannot be added to a payment run.'],
                    ]);
                }

                if (! in_array($schedule_line->status, [PaymentScheduleStatus::Open, PaymentScheduleStatus::Partial], true)) {
                    throw ValidationException::withMessages([
                        'payment_schedule_line_ids' => ['Only open or partial payment schedule lines can be added to a payment run.'],
                    ]);
                }

                $invoice = $schedule_line->invoice;
                if ($invoice === null
                    || $invoice->direction !== InvoiceDirection::Purchase
                    || $invoice->invoice_type !== InvoiceType::Invoice) {
                    throw ValidationException::withMessages([
                        'payment_schedule_line_ids' => ['Only ordinary purchase invoice schedule lines can be paid by supplier payment runs.'],
                    ]);
                }

                $party = $invoice->party;
                if ($party === null || ! $party->is_supplier) {
                    throw ValidationException::withMessages([
                        'party_id' => ['The schedule line must belong to a supplier.'],
                    ]);
                }

                $party_bank_account = $this->defaultPartyBankAccount((int) $company_id, (int) $party->id);

                PaymentRunLine::query()->create([
                    'company_id' => $company_id,
                    'payment_run_id' => $run->id,
                    'payment_schedule_line_id' => $schedule_line->id,
                    'party_id' => $party->id,
                    'party_bank_account_id' => $party_bank_account->id,
                    'amount_doc' => $residual_doc,
                    'currency_doc' => $schedule_line->currency_doc,
                    'amount_local' => $residual_local,
                    'currency_local' => $schedule_line->currency_local,
                    'due_date' => $schedule_line->due_date,
                    'beneficiary_name' => $party_bank_account->beneficiary_name,
                    'beneficiary_iban' => $party_bank_account->iban,
                    'beneficiary_bic' => $party_bank_account->bic,
                    'remittance_information' => $this->remittanceInformation($schedule_line),
                    'status' => PaymentRunLineStatus::Included,
                ]);

                $total_doc += (float) $residual_doc;
                $total_local += (float) $residual_local;
            }

            $run->total_amount_doc = $this->round4($total_doc);
            $run->total_amount_local = $this->round4($total_local);
            $run->save();

            return $run->fresh('lines') ?? $run;
        });
    }

    private function defaultPartyBankAccount(int $company_id, int $party_id): PartyBankAccount
    {
        $accounts = PartyBankAccount::query()
            ->where('company_id', $company_id)
            ->where('party_id', $party_id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->get();

        if ($accounts->count() !== 1) {
            throw ValidationException::withMessages([
                'party_bank_account_id' => ['The supplier must have exactly one active default bank account.'],
            ]);
        }

        return $accounts->firstOrFail();
    }

    /**
     * @return numeric-string
     */
    private function residualAmount(PaymentScheduleLine $schedule_line): string
    {
        return $this->round4((float) $schedule_line->amount_doc - (float) $schedule_line->paid_amount_doc);
    }

    private function remittanceInformation(PaymentScheduleLine $schedule_line): string
    {
        $reference = $schedule_line->invoice?->reference ?? 'Invoice #' . $schedule_line->invoice_id;

        return mb_substr($reference . ' due ' . $schedule_line->due_date->toDateString(), 0, 140);
    }

    /**
     * @return numeric-string
     */
    private function round4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }
}
