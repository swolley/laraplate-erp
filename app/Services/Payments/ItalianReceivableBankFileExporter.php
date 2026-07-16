<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\PartyBankAccount;
use Modules\ERP\Models\PaymentScheduleLine;

final class ItalianReceivableBankFileExporter
{
    /**
     * @param  iterable<int, PaymentScheduleLine>  $schedule_lines
     */
    public function exportRiba(iterable $schedule_lines, BankAccount $creditor_bank): string
    {
        $lines = $this->eligibleReceivables($schedule_lines);
        $records = [];
        $total_cents = 0;

        $records[] = $this->record('IB', [
            $this->field('RIBA-' . now()->format('YmdHis'), 20),
            $this->field($creditor_bank->iban ?? '', 34),
            $this->field($creditor_bank->company?->name ?? 'COMPANY', 40),
        ]);

        foreach ($lines as $line) {
            $line->loadMissing(['invoice.party.bank_accounts']);
            $amount_cents = $this->amountCents((string) $this->openAmount($line));
            $total_cents += $amount_cents;
            $party = $line->invoice?->party;

            $records[] = $this->record('20', [
                $this->field((string) $line->invoice?->reference ?: 'SCHEDULE-' . $line->id, 20),
                $this->field($party?->name ?? 'CUSTOMER', 40),
                $this->field($line->due_date->format('Ymd'), 8),
                $this->field((string) $amount_cents, 15, '0', STR_PAD_LEFT),
                $this->field($line->currency_doc, 3),
                $this->field($this->abiFromIban((string) $creditor_bank->iban), 5, '0', STR_PAD_LEFT),
                $this->field($this->cabFromIban((string) $creditor_bank->iban), 5, '0', STR_PAD_LEFT),
            ]);
        }

        $records[] = $this->record('EF', [
            $this->field((string) $lines->count(), 7, '0', STR_PAD_LEFT),
            $this->field((string) $total_cents, 15, '0', STR_PAD_LEFT),
        ]);

        return implode("\r\n", $records) . "\r\n";
    }

    /**
     * @param  iterable<int, PaymentScheduleLine>  $schedule_lines
     */
    public function exportSddCore(iterable $schedule_lines, BankAccount $creditor_bank, string $creditor_identifier): string
    {
        if (trim($creditor_identifier) === '') {
            throw ValidationException::withMessages([
                'creditor_identifier' => ['A creditor identifier is required for SDD export.'],
            ]);
        }

        $lines = $this->eligibleReceivables($schedule_lines);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElement('Document');
        $root->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');
        $document->appendChild($root);

        $initiation = $this->append($document, $root, 'CstmrDrctDbtInitn');
        $group_header = $this->append($document, $initiation, 'GrpHdr');
        $this->append($document, $group_header, 'MsgId', 'SDD-' . now()->format('YmdHis'));
        $this->append($document, $group_header, 'CreDtTm', now()->toISOString());
        $this->append($document, $group_header, 'NbOfTxs', (string) $lines->count());
        $this->append($document, $group_header, 'CtrlSum', $this->amount2($lines->sum(fn (PaymentScheduleLine $line): float => $this->openAmount($line))));
        $initiating_party = $this->append($document, $group_header, 'InitgPty');
        $this->append($document, $initiating_party, 'Nm', $creditor_bank->company?->name ?? 'Company');

        $payment_info = $this->append($document, $initiation, 'PmtInf');
        $this->append($document, $payment_info, 'PmtInfId', 'SDD-' . now()->format('YmdHis'));
        $this->append($document, $payment_info, 'PmtMtd', 'DD');
        $this->append($document, $payment_info, 'NbOfTxs', (string) $lines->count());
        $this->append($document, $payment_info, 'CtrlSum', $this->amount2($lines->sum(fn (PaymentScheduleLine $line): float => $this->openAmount($line))));
        $payment_type = $this->append($document, $payment_info, 'PmtTpInf');
        $service_level = $this->append($document, $payment_type, 'SvcLvl');
        $this->append($document, $service_level, 'Cd', 'SEPA');
        $local_instrument = $this->append($document, $payment_type, 'LclInstrm');
        $this->append($document, $local_instrument, 'Cd', 'CORE');
        $this->append($document, $payment_type, 'SeqTp', 'RCUR');
        $this->append($document, $payment_info, 'ReqdColltnDt', $lines->min(fn (PaymentScheduleLine $line): string => $line->due_date->toDateString()));
        $creditor = $this->append($document, $payment_info, 'Cdtr');
        $this->append($document, $creditor, 'Nm', $creditor_bank->company?->name ?? 'Company');
        $creditor_account = $this->append($document, $payment_info, 'CdtrAcct');
        $creditor_account_id = $this->append($document, $creditor_account, 'Id');
        $this->append($document, $creditor_account_id, 'IBAN', (string) $creditor_bank->iban);
        $creditor_scheme = $this->append($document, $payment_info, 'CdtrSchmeId');
        $id = $this->append($document, $creditor_scheme, 'Id');
        $private_id = $this->append($document, $id, 'PrvtId');
        $other = $this->append($document, $private_id, 'Othr');
        $this->append($document, $other, 'Id', $creditor_identifier);

        foreach ($lines as $line) {
            $this->appendSddTransaction($document, $payment_info, $line);
        }

        return $document->saveXML() ?: '';
    }

    /**
     * @param  iterable<int, PaymentScheduleLine>  $schedule_lines
     * @return Collection<int, PaymentScheduleLine>
     */
    private function eligibleReceivables(iterable $schedule_lines): Collection
    {
        $lines = collect($schedule_lines)->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'schedule_lines' => ['At least one receivable schedule line is required.'],
            ]);
        }

        $lines->each(function (PaymentScheduleLine $line): void {
            $line->loadMissing('invoice.party.bank_accounts');

            if ($line->invoice?->direction !== InvoiceDirection::Sale) {
                throw ValidationException::withMessages([
                    'schedule_lines' => ['Only sale invoice schedule lines can be exported as receivables.'],
                ]);
            }

            if (! in_array($line->status, [PaymentScheduleStatus::Open, PaymentScheduleStatus::Partial], true)) {
                throw ValidationException::withMessages([
                    'schedule_lines' => ['Only open or partial schedule lines can be exported as receivables.'],
                ]);
            }
        });

        return $lines;
    }

    private function appendSddTransaction(DOMDocument $document, DOMElement $parent, PaymentScheduleLine $line): void
    {
        $bank_account = $this->defaultPartyBankAccount($line);

        if ($bank_account->direct_debit_mandate_reference === null || $bank_account->direct_debit_mandate_signed_on === null) {
            throw ValidationException::withMessages([
                'mandate' => ['SDD export requires mandate reference and signature date on the customer bank account.'],
            ]);
        }

        $transaction = $this->append($document, $parent, 'DrctDbtTxInf');
        $payment_id = $this->append($document, $transaction, 'PmtId');
        $this->append($document, $payment_id, 'EndToEndId', 'SCHEDULE-' . $line->id);
        $amount = $this->append($document, $transaction, 'InstdAmt', $this->amount2($this->openAmount($line)));
        $amount->setAttribute('Ccy', $line->currency_doc);
        $direct_debit = $this->append($document, $transaction, 'DrctDbtTx');
        $mandate = $this->append($document, $direct_debit, 'MndtRltdInf');
        $this->append($document, $mandate, 'MndtId', $bank_account->direct_debit_mandate_reference);
        $this->append($document, $mandate, 'DtOfSgntr', $bank_account->direct_debit_mandate_signed_on->toDateString());
        $debtor = $this->append($document, $transaction, 'Dbtr');
        $this->append($document, $debtor, 'Nm', $line->invoice?->party?->name ?? 'Customer');
        $debtor_account = $this->append($document, $transaction, 'DbtrAcct');
        $debtor_account_id = $this->append($document, $debtor_account, 'Id');
        $this->append($document, $debtor_account_id, 'IBAN', $bank_account->iban);
        $remittance = $this->append($document, $transaction, 'RmtInf');
        $this->append($document, $remittance, 'Ustrd', (string) ($line->invoice?->reference ?? 'Schedule ' . $line->id));
    }

    private function defaultPartyBankAccount(PaymentScheduleLine $line): PartyBankAccount
    {
        $account = $line->invoice?->party?->bank_accounts
            ->where('is_active', true)
            ->sortByDesc('is_default')
            ->first();

        if (! $account instanceof PartyBankAccount) {
            throw ValidationException::withMessages([
                'party_bank_account' => ['A customer bank account is required for this bank export.'],
            ]);
        }

        return $account;
    }

    private function openAmount(PaymentScheduleLine $line): float
    {
        return max(0.0, (float) $line->amount_doc - (float) $line->paid_amount_doc);
    }

    private function append(DOMDocument $document, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $document->createElement($name);

        if ($value !== null) {
            $element->appendChild($document->createTextNode($value));
        }

        $parent->appendChild($element);

        return $element;
    }

    /**
     * @param  list<string>  $fields
     */
    private function record(string $type, array $fields): string
    {
        return $type . implode('', $fields);
    }

    private function field(string $value, int $length, string $pad = ' ', int $direction = STR_PAD_RIGHT): string
    {
        $clean = preg_replace('/[^A-Za-z0-9 .,_\/-]/', '', $value) ?? '';

        return substr(str_pad(strtoupper($clean), $length, $pad, $direction), 0, $length);
    }

    private function amountCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function amount2(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    private function abiFromIban(string $iban): string
    {
        return substr(strtoupper(str_replace(' ', '', $iban)), 5, 5) ?: '';
    }

    private function cabFromIban(string $iban): string
    {
        return substr(strtoupper(str_replace(' ', '', $iban)), 10, 5) ?: '';
    }
}
