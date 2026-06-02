<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Payment;
use Modules\ERP\Services\Banking\BankReconciliationService;
use Override;
use UnitEnum;

final class BankReconciliationPage extends Page
{
    public ?array $data = [];

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 64;

    #[Override]
    protected static ?string $slug = 'bank-reconciliation';

    #[Override]
    protected static ?string $navigationLabel = 'Bank Reconciliation';

    #[Override]
    protected static ?string $title = 'Bank Reconciliation';

    #[Override]
    protected string $view = 'erp::filament.pages.bank-reconciliation';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('bank_statement_line_id')
                    ->label('Statement line')
                    ->options(fn (): array => $this->lineOptions())
                    ->searchable()
                    ->required()
                    ->live(),
                Select::make('payment_id')
                    ->label('Payment')
                    ->options(fn (): array => $this->paymentOptions())
                    ->searchable()
                    ->required(),
            ]);
    }

    public function matchSelected(): void
    {
        $state = $this->form->getState();
        $line = BankStatementLine::query()->findOrFail((int) $state['bank_statement_line_id']);
        $payment = Payment::query()->findOrFail((int) $state['payment_id']);

        app(BankReconciliationService::class)->matchPayment($line, $payment);

        $this->form->fill();

        Notification::make()
            ->title('Bank statement line matched')
            ->success()
            ->send();
    }

    public function ignoreSelected(): void
    {
        $state = $this->form->getState();
        $line = BankStatementLine::query()->findOrFail((int) $state['bank_statement_line_id']);

        app(BankReconciliationService::class)->ignore($line);

        $this->form->fill();

        Notification::make()
            ->title('Bank statement line ignored')
            ->success()
            ->send();
    }

    /**
     * @return array<int, string>
     */
    public function unmatchedLines(): array
    {
        return BankStatementLine::query()
            ->with('bank_statement.bank_account')
            ->where('status', BankStatementLineStatus::Imported->value)
            ->whereNull('matched_payment_id')
            ->orderByDesc('booked_at')
            ->limit(25)
            ->get()
            ->mapWithKeys(static fn (BankStatementLine $line): array => [
                (int) $line->getKey() => sprintf(
                    '%s | %s | %s %s | %s',
                    $line->booked_at?->format('Y-m-d') ?? '-',
                    $line->bank_statement?->bank_account?->name ?? 'Bank account',
                    $line->amount_doc,
                    $line->currency_doc,
                    $line->reference ?? $line->description ?? 'Line #' . $line->getKey(),
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function suggestedPaymentsForLine(int $bank_statement_line_id): array
    {
        /** @var BankStatementLine $line */
        $line = BankStatementLine::query()->findOrFail($bank_statement_line_id);

        return app(BankReconciliationService::class)
            ->suggestPayments($line)
            ->take(5)
            ->mapWithKeys(static fn (Payment $payment): array => [
                (int) $payment->getKey() => sprintf(
                    '%s | %s | %s %s | %s',
                    $payment->payment_date?->format('Y-m-d') ?? '-',
                    $payment->party?->name ?? 'Party',
                    $payment->amount_doc,
                    $payment->currency_doc,
                    $payment->reference ?? 'Payment #' . $payment->getKey(),
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function lineOptions(): array
    {
        return $this->unmatchedLines();
    }

    /**
     * @return array<int, string>
     */
    private function paymentOptions(): array
    {
        return Payment::query()
            ->with('party')
            ->orderByDesc('payment_date')
            ->limit(100)
            ->get()
            ->mapWithKeys(static fn (Payment $payment): array => [
                (int) $payment->getKey() => sprintf(
                    '%s | %s | %s %s | %s',
                    $payment->payment_date?->format('Y-m-d') ?? '-',
                    $payment->party?->name ?? 'Party',
                    $payment->amount_doc,
                    $payment->currency_doc,
                    $payment->reference ?? 'Payment #' . $payment->getKey(),
                ),
            ])
            ->all();
    }
}
