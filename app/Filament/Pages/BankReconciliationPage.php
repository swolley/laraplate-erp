<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Models\Account;
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
                    ->live()
                    ->required(),
                TextInput::make('difference_amount')
                    ->label('Difference')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn (): string => $this->currentDifferenceAmount()),
                Select::make('expense_account_id')
                    ->label('Difference account')
                    ->options(fn (): array => $this->expenseAccountOptions())
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

    public function matchWithDifference(): void
    {
        $state = $this->form->getState();
        $line = BankStatementLine::query()->findOrFail((int) $state['bank_statement_line_id']);
        $payment = Payment::query()->findOrFail((int) $state['payment_id']);
        $expense_account_id = (int) $state['expense_account_id'];

        app(BankReconciliationService::class)->matchPaymentWithDifference($line, $payment, $expense_account_id);

        $this->form->fill();

        Notification::make()
            ->title('Matched with difference')
            ->success()
            ->send();
    }

    public function currentDifferenceAmount(): string
    {
        $line_id = (int) ($this->data['bank_statement_line_id'] ?? 0);
        $payment_id = (int) ($this->data['payment_id'] ?? 0);

        if ($line_id === 0 || $payment_id === 0) {
            return '0.0000';
        }

        $line = BankStatementLine::query()->find($line_id);
        $payment = Payment::query()->find($payment_id);

        if ($line === null || $payment === null) {
            return '0.0000';
        }

        $expected_sign = $payment->direction === PaymentDirection::Inbound ? 1 : -1;
        $expected_line_amount = $expected_sign * (float) $payment->amount_doc;

        return number_format(round((float) $line->amount_doc - $expected_line_amount, 4), 4, '.', '');
    }

    public function canMatchWithDifference(): bool
    {
        $difference = $this->currentDifferenceAmount();

        return abs((float) $difference) > 0.00005
            && (int) ($this->data['bank_statement_line_id'] ?? 0) > 0
            && (int) ($this->data['payment_id'] ?? 0) > 0
            && (int) ($this->data['expense_account_id'] ?? 0) > 0;
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

    /**
     * @return array<int, string>
     */
    private function expenseAccountOptions(): array
    {
        $line_id = (int) ($this->data['bank_statement_line_id'] ?? 0);
        $company_id = null;

        if ($line_id !== 0) {
            $company_id = BankStatementLine::query()->whereKey($line_id)->value('company_id');
        }

        return Account::query()
            ->when($company_id !== null, static fn (Builder $query): Builder => $query->where('company_id', $company_id))
            ->where('kind', AccountKind::Expense->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->limit(100)
            ->get()
            ->mapWithKeys(static fn (Account $account): array => [
                (int) $account->getKey() => sprintf('%s | %s', $account->code, $account->name),
            ])
            ->all();
    }
}
