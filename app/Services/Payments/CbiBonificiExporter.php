<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\PaymentRunLineStatus;
use Modules\ERP\Casts\PaymentRunStatus;
use Modules\ERP\Models\PaymentRun;
use Modules\ERP\Models\PaymentRunLine;
use Modules\ERP\Support\ConnectionScopedTransaction;

final class CbiBonificiExporter
{
    public function export(PaymentRun $payment_run): string
    {
        return ConnectionScopedTransaction::run($payment_run, function () use ($payment_run): string {
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

            $content = $this->build($run);

            $run->status = PaymentRunStatus::Exported;
            $run->exported_at = now();
            $run->export_file_name = sprintf('payment-run-%s-cbi-bonifici.txt', $run->id);
            $run->export_checksum = hash('sha256', $content);
            $run->save();

            PaymentRunLine::query()
                ->where('payment_run_id', $run->id)
                ->where('status', PaymentRunLineStatus::Included)
                ->update(['status' => PaymentRunLineStatus::Exported->value]);

            return $content;
        });
    }

    private function build(PaymentRun $run): string
    {
        $records = [];
        $records[] = $this->record('IB', [
            $this->field('PAYRUN' . $run->id, 20),
            $this->field($run->execution_date->format('Ymd'), 8),
            $this->field($run->bank_account?->company?->name ?? 'COMPANY', 40),
            $this->field($this->cleanIban((string) $run->bank_account?->iban), 34),
            $this->amountCents((string) $run->total_amount_doc, 15),
            $this->field((string) $run->currency, 3),
        ]);

        foreach ($run->lines as $line) {
            $records[] = $this->record('14', [
                $this->field('PAYRUN' . $line->payment_run_id . '-' . $line->id, 20),
                $this->field($line->beneficiary_name, 40),
                $this->field($this->cleanIban($line->beneficiary_iban), 34),
                $this->field($line->beneficiary_bic ?? '', 11),
                $this->amountCents((string) $line->amount_doc, 15),
                $this->field($line->currency_doc, 3),
                $this->field($line->remittance_information ?? '', 120),
            ]);
        }

        $records[] = $this->record('EF', [
            $this->field((string) $run->lines->count(), 7, '0', STR_PAD_LEFT),
            $this->amountCents((string) $run->total_amount_doc, 15),
            $this->field(hash('sha256', implode("\n", $records)), 64),
        ]);

        return implode("\r\n", $records) . "\r\n";
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

    private function amountCents(string $amount, int $length): string
    {
        $cents = (int) round(((float) $amount) * 100);

        return str_pad((string) $cents, $length, '0', STR_PAD_LEFT);
    }

    private function cleanIban(string $iban): string
    {
        return strtoupper(str_replace(' ', '', $iban));
    }
}
