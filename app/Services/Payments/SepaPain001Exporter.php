<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\PaymentRunLineStatus;
use Modules\ERP\Casts\PaymentRunStatus;
use Modules\ERP\Models\PaymentRun;
use Modules\ERP\Models\PaymentRunLine;

final class SepaPain001Exporter
{
    public function export(PaymentRun $payment_run): string
    {
        return DB::transaction(function () use ($payment_run): string {
            /** @var PaymentRun $run */
            $run = PaymentRun::query()
                ->with(['bank_account.company', 'lines'])
                ->whereKey($payment_run->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($run->status !== PaymentRunStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => ['Only approved payment runs can be exported.'],
                ]);
            }

            if ($run->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Cannot export an empty payment run.'],
                ]);
            }

            $xml = $this->buildXml($run);

            $run->status = PaymentRunStatus::Exported;
            $run->exported_at = now();
            $run->export_file_name = sprintf('payment-run-%s-pain001.xml', $run->id);
            $run->export_checksum = hash('sha256', $xml);
            $run->save();

            PaymentRunLine::query()
                ->where('payment_run_id', $run->id)
                ->where('status', PaymentRunLineStatus::Included)
                ->update(['status' => PaymentRunLineStatus::Exported->value]);

            return $xml;
        });
    }

    private function buildXml(PaymentRun $run): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement('Document');
        $root->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.03');
        $document->appendChild($root);

        $customer_transfer = $this->append($document, $root, 'CstmrCdtTrfInitn');
        $group_header = $this->append($document, $customer_transfer, 'GrpHdr');
        $this->append($document, $group_header, 'MsgId', 'PAYRUN-' . $run->id);
        $this->append($document, $group_header, 'CreDtTm', now()->toISOString());
        $this->append($document, $group_header, 'NbOfTxs', (string) $run->lines->count());
        $this->append($document, $group_header, 'CtrlSum', $this->amount2((float) $run->total_amount_doc));
        $initiating_party = $this->append($document, $group_header, 'InitgPty');
        $this->append($document, $initiating_party, 'Nm', $run->bank_account?->company?->name ?? 'Company');

        $payment_information = $this->append($document, $customer_transfer, 'PmtInf');
        $this->append($document, $payment_information, 'PmtInfId', 'PAYRUN-' . $run->id);
        $this->append($document, $payment_information, 'PmtMtd', 'TRF');
        $this->append($document, $payment_information, 'NbOfTxs', (string) $run->lines->count());
        $this->append($document, $payment_information, 'CtrlSum', $this->amount2((float) $run->total_amount_doc));
        $payment_type_information = $this->append($document, $payment_information, 'PmtTpInf');
        $service_level = $this->append($document, $payment_type_information, 'SvcLvl');
        $this->append($document, $service_level, 'Cd', 'SEPA');
        $this->append($document, $payment_information, 'ReqdExctnDt', $run->execution_date->toDateString());
        $debtor = $this->append($document, $payment_information, 'Dbtr');
        $this->append($document, $debtor, 'Nm', $run->bank_account?->company?->name ?? 'Company');
        $debtor_account = $this->append($document, $payment_information, 'DbtrAcct');
        $debtor_account_id = $this->append($document, $debtor_account, 'Id');
        $this->append($document, $debtor_account_id, 'IBAN', (string) $run->bank_account?->iban);
        $debtor_agent = $this->append($document, $payment_information, 'DbtrAgt');
        $debtor_financial_institution = $this->append($document, $debtor_agent, 'FinInstnId');
        $this->append($document, $debtor_financial_institution, 'Othr')->appendChild($document->createElement('Id', 'NOTPROVIDED'));
        $this->append($document, $payment_information, 'ChrgBr', 'SLEV');

        foreach ($run->lines as $line) {
            $this->appendTransaction($document, $payment_information, $line);
        }

        return $document->saveXML() ?: '';
    }

    private function appendTransaction(DOMDocument $document, DOMElement $parent, PaymentRunLine $line): void
    {
        $transaction = $this->append($document, $parent, 'CdtTrfTxInf');
        $payment_id = $this->append($document, $transaction, 'PmtId');
        $this->append($document, $payment_id, 'EndToEndId', 'PAYRUN-' . $line->payment_run_id . '-' . $line->id);
        $amount = $this->append($document, $transaction, 'Amt');
        $instructed_amount = $this->append($document, $amount, 'InstdAmt', $this->amount2((float) $line->amount_doc));
        $instructed_amount->setAttribute('Ccy', $line->currency_doc);

        if ($line->beneficiary_bic !== null && trim($line->beneficiary_bic) !== '') {
            $creditor_agent = $this->append($document, $transaction, 'CdtrAgt');
            $creditor_financial_institution = $this->append($document, $creditor_agent, 'FinInstnId');
            $this->append($document, $creditor_financial_institution, 'BIC', $line->beneficiary_bic);
        }

        $creditor = $this->append($document, $transaction, 'Cdtr');
        $this->append($document, $creditor, 'Nm', $line->beneficiary_name);
        $creditor_account = $this->append($document, $transaction, 'CdtrAcct');
        $creditor_account_id = $this->append($document, $creditor_account, 'Id');
        $this->append($document, $creditor_account_id, 'IBAN', $line->beneficiary_iban);

        if ($line->remittance_information !== null && trim($line->remittance_information) !== '') {
            $remittance = $this->append($document, $transaction, 'RmtInf');
            $this->append($document, $remittance, 'Ustrd', $line->remittance_information);
        }
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

    private function amount2(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
