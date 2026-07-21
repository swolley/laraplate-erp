<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PartnerPools\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Filament\Resources\PartnerPools\PartnerPoolResource;
use Modules\ERP\Models\Movement;
use Modules\ERP\Services\Cash\PartnerPoolSettlementService;
use Override;

final class EditPartnerPool extends EditRecord
{
    #[Override]
    protected static string $resource = PartnerPoolResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('allocate_expense')
                ->label('Split expense')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->schema([
                    Select::make('movement_id')
                        ->label('Expense movement')
                        ->options(fn (): array => Movement::query()
                            ->where('company_id', $this->record->company_id)
                            ->where('currency_doc', $this->record->currency)
                            ->where('type', MovementType::Expense->value)
                            ->orderByDesc('occurred_on')
                            ->get()->mapWithKeys(fn (Movement $movement): array => [
                                (int) $movement->id => $movement->occurred_on->format('Y-m-d') . ' · ' . $movement->amount_doc . ' ' . $movement->currency_doc . ' · ' . ($movement->description ?: '#'.$movement->id),
                            ])->all())
                        ->searchable()->required(),
                    Repeater::make('shares')->schema([
                        Select::make('user_id')
                            ->options(fn (): array => $this->record->members()->pluck('name', 'users.id')->all())
                            ->distinct()->required(),
                        TextInput::make('owed')->numeric()->minValue(0)->required(),
                        TextInput::make('paid')->numeric()->minValue(0)->required(),
                    ])->minItems(1)->columns(3)->required(),
                ])
                ->action(function (array $data): void {
                    $shares = collect($data['shares'])->mapWithKeys(static fn (array $share): array => [
                        (int) $share['user_id'] => ['owed' => $share['owed'], 'paid' => $share['paid']],
                    ])->all();
                    resolve(PartnerPoolSettlementService::class)->allocate(
                        Movement::query()->findOrFail($data['movement_id']),
                        $this->record,
                        $shares,
                    );
                    Notification::make()->title('Expense split saved')->success()->send();
                }),
            Action::make('settle_up')
                ->label('Settle up')
                ->icon(Heroicon::OutlinedBanknotes)
                ->schema([
                    Select::make('from_user_id')->label('Paid by')->options(fn (): array => $this->record->members()->pluck('name', 'users.id')->all())->required(),
                    Select::make('to_user_id')->label('Paid to')->options(fn (): array => $this->record->members()->pluck('name', 'users.id')->all())->different('from_user_id')->required(),
                    TextInput::make('amount')->numeric()->minValue(0.0001)->required(),
                    DatePicker::make('occurred_on')->default(now())->disabled(),
                    TextInput::make('description')->maxLength(255),
                ])
                ->action(function (array $data): void {
                    resolve(PartnerPoolSettlementService::class)->settle(
                        $this->record,
                        (int) $data['from_user_id'],
                        (int) $data['to_user_id'],
                        (string) $data['amount'],
                        $data['description'] ?? null,
                    );
                    Notification::make()->title('Settlement recorded')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}
