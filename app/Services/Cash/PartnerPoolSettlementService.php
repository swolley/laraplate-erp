<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Cash;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Models\Movement;
use Modules\ERP\Models\MovementAllocation;
use Modules\ERP\Models\PartnerPool;
use Modules\ERP\Models\PoolTransaction;
use Modules\ERP\Support\Decimal;

final class PartnerPoolSettlementService
{
    /**
     * @param array<int, array{owed: string|int|float, paid: string|int|float}> $shares
     */
    public function allocate(Movement $movement, PartnerPool $pool, array $shares): void
    {
        DB::transaction(function () use ($movement, $pool, $shares): void {
            $pool = PartnerPool::query()->lockForUpdate()->findOrFail($pool->getKey());
            $movement = Movement::query()->lockForUpdate()->findOrFail($movement->getKey());

            if ($movement->type !== MovementType::Expense) {
                $this->fail('movement', 'Only expense movements can be split between pool members.');
            }
            if ((int) $movement->company_id !== (int) $pool->company_id) {
                $this->fail('partner_pool_id', 'Movement and partner pool must belong to the same company.');
            }
            if (strtoupper($movement->currency_doc) !== strtoupper($pool->currency)) {
                $this->fail('currency', 'Movement and partner pool currencies must match.');
            }
            if ($shares === []) {
                $this->fail('shares', 'At least one pool member must participate in the split.');
            }

            $member_ids = $pool->members()->pluck('users.id')->map(static fn (mixed $id): int => (int) $id)->all();
            $owed_total = '0.0000';
            $paid_total = '0.0000';

            foreach ($shares as $user_id => $share) {
                if (! in_array((int) $user_id, $member_ids, true)) {
                    $this->fail('shares', "User {$user_id} is not a member of this pool.");
                }
                $owed = Decimal::format((string) $share['owed']);
                $paid = Decimal::format((string) $share['paid']);
                if (Decimal::isNegative($owed) || Decimal::isNegative($paid)) {
                    $this->fail('shares', 'Split amounts cannot be negative.');
                }
                $owed_total = Decimal::add($owed_total, $owed);
                $paid_total = Decimal::add($paid_total, $paid);
            }

            $amount = Decimal::format((string) $movement->amount_doc);
            if ($owed_total !== $amount || $paid_total !== $amount) {
                $this->fail('shares', 'Both owed and paid split totals must equal the movement amount.');
            }

            MovementAllocation::query()->where('movement_id', $movement->getKey())->delete();
            foreach ($shares as $user_id => $share) {
                MovementAllocation::query()->create([
                    'partner_pool_id' => $pool->getKey(),
                    'movement_id' => $movement->getKey(),
                    'user_id' => $user_id,
                    'owed_amount' => Decimal::format((string) $share['owed']),
                    'paid_amount' => Decimal::format((string) $share['paid']),
                ]);
            }
        });
    }

    /** @return array<int, string> user_id => signed balance */
    public function balances(PartnerPool $pool): array
    {
        $balances = $pool->members()->pluck('users.id')->mapWithKeys(
            static fn (mixed $id): array => [(int) $id => '0.0000'],
        )->all();

        foreach ($pool->allocations()->get() as $allocation) {
            $user_id = (int) $allocation->user_id;
            $balances[$user_id] ??= '0.0000';
            $balances[$user_id] = Decimal::add(
                $balances[$user_id],
                Decimal::sub((string) $allocation->paid_amount, (string) $allocation->owed_amount),
            );
        }

        foreach ($pool->transactions()->whereNotNull('confirmed_at')->get() as $transaction) {
            $from = (int) $transaction->from_user_id;
            $to = (int) $transaction->to_user_id;
            $balances[$from] = Decimal::add($balances[$from] ?? '0.0000', (string) $transaction->amount);
            $balances[$to] = Decimal::sub($balances[$to] ?? '0.0000', (string) $transaction->amount);
        }

        ksort($balances);

        return $balances;
    }

    /** @return list<array{from_user_id: int, to_user_id: int, amount: string, currency: string}> */
    public function suggestSettleUp(PartnerPool $pool): array
    {
        $debtors = [];
        $creditors = [];
        foreach ($this->balances($pool) as $user_id => $balance) {
            if (Decimal::isNegative($balance)) {
                $debtors[] = ['id' => $user_id, 'amount' => Decimal::abs($balance)];
            } elseif (! Decimal::isZero($balance)) {
                $creditors[] = ['id' => $user_id, 'amount' => $balance];
            }
        }

        $suggestions = [];
        $debtor_index = 0;
        $creditor_index = 0;
        while (isset($debtors[$debtor_index], $creditors[$creditor_index])) {
            $debt = $debtors[$debtor_index]['amount'];
            $credit = $creditors[$creditor_index]['amount'];
            $amount = BigDecimal::of($debt)->isLessThanOrEqualTo(BigDecimal::of($credit)) ? $debt : $credit;
            $suggestions[] = [
                'from_user_id' => $debtors[$debtor_index]['id'],
                'to_user_id' => $creditors[$creditor_index]['id'],
                'amount' => Decimal::format($amount),
                'currency' => strtoupper($pool->currency),
            ];
            $debtors[$debtor_index]['amount'] = Decimal::sub($debt, $amount);
            $creditors[$creditor_index]['amount'] = Decimal::sub($credit, $amount);
            if (Decimal::isZero($debtors[$debtor_index]['amount'])) { $debtor_index++; }
            if (Decimal::isZero($creditors[$creditor_index]['amount'])) { $creditor_index++; }
        }

        return $suggestions;
    }

    public function settle(PartnerPool $pool, int $from_user_id, int $to_user_id, string $amount, ?string $description = null): PoolTransaction
    {
        return DB::transaction(function () use ($pool, $from_user_id, $to_user_id, $amount, $description): PoolTransaction {
            $pool = PartnerPool::query()->lockForUpdate()->findOrFail($pool->getKey());
            if ($from_user_id === $to_user_id) {
                $this->fail('to_user_id', 'Settlement participants must be different.');
            }
            $amount = Decimal::format($amount);
            if (Decimal::isZero($amount) || Decimal::isNegative($amount)) {
                $this->fail('amount', 'Settlement amount must be greater than zero.');
            }
            $balances = $this->balances($pool);
            $debt = Decimal::abs($balances[$from_user_id] ?? '0.0000');
            $credit = $balances[$to_user_id] ?? '0.0000';
            if (! Decimal::isNegative($balances[$from_user_id] ?? '0.0000') || Decimal::isNegative($credit)
                || BigDecimal::of($amount)->isGreaterThan(BigDecimal::of($debt))
                || BigDecimal::of($amount)->isGreaterThan(BigDecimal::of($credit))) {
                $this->fail('amount', 'Settlement exceeds the current debtor/creditor balances.');
            }

            return PoolTransaction::query()->create([
                'partner_pool_id' => $pool->getKey(),
                'from_user_id' => $from_user_id,
                'to_user_id' => $to_user_id,
                'amount' => $amount,
                'currency' => strtoupper($pool->currency),
                'occurred_on' => now()->toDateString(),
                'confirmed_at' => now(),
                'description' => $description,
            ]);
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
