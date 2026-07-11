<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRuns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\PaymentScheduleLine;

final class PaymentRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->options(static fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit')
                    ->live(),
                Select::make('bank_account_id')
                    ->options(static fn (callable $get): array => BankAccount::query()
                        ->when($get('company_id'), static fn (Builder $query, mixed $company_id): Builder => $query->where('company_id', $company_id))
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                DatePicker::make('execution_date')
                    ->required()
                    ->default(now())
                    ->disabledOn('edit'),
                Select::make('payment_schedule_line_ids')
                    ->label('Supplier schedule lines')
                    ->options(static fn (callable $get): array => self::scheduleLineOptions($get('company_id')))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visibleOn('create'),
                TextInput::make('status')
                    ->disabled()
                    ->visibleOn('edit'),
                TextInput::make('currency')
                    ->disabled()
                    ->visibleOn('edit'),
                TextInput::make('total_amount_doc')
                    ->numeric()
                    ->disabled()
                    ->visibleOn('edit'),
                Repeater::make('lines')
                    ->relationship('lines')
                    ->schema([
                        TextInput::make('beneficiary_name')->disabled(),
                        TextInput::make('beneficiary_iban')->disabled(),
                        TextInput::make('amount_doc')->disabled(),
                        TextInput::make('currency_doc')->disabled(),
                        TextInput::make('remittance_information')->disabled()->columnSpanFull(),
                    ])
                    ->disabled()
                    ->defaultItems(0)
                    ->visibleOn('edit')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function scheduleLineOptions(mixed $company_id): array
    {
        return PaymentScheduleLine::query()
            ->with(['invoice.party'])
            ->when($company_id, static fn (Builder $query, mixed $company_id): Builder => $query->where('company_id', $company_id))
            ->whereIn('status', [PaymentScheduleStatus::Open->value, PaymentScheduleStatus::Partial->value])
            ->whereHas('invoice', static function (Builder $query): void {
                $query->where('direction', InvoiceDirection::Purchase->value)
                    ->where('invoice_type', InvoiceType::Invoice->value);
            })
            ->orderBy('due_date')
            ->limit(100)
            ->get()
            ->mapWithKeys(static function (PaymentScheduleLine $line): array {
                $party = $line->invoice?->party?->name ?? 'Supplier';
                $reference = $line->invoice?->reference ?? 'Invoice #' . $line->invoice_id;
                $residual = number_format((float) $line->amount_doc - (float) $line->paid_amount_doc, 4, '.', '');

                return [
                    (int) $line->id => "{$party} · {$reference} · {$line->due_date->toDateString()} · {$residual} {$line->currency_doc}",
                ];
            })
            ->all();
    }
}
