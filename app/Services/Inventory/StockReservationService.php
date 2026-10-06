<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Inventory;

use Brick\Math\Exception\MathException;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Exceptions\InsufficientStockException;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockReservation;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Support\Decimal;

/**
 * The single contract for item availability: `available = on hand - live reservations`.
 *
 * A reservation is live when it is `hard`, or `soft` with `expires_at` null or in the future; an
 * expired soft is ignored lazily here, whether or not a sweep has already closed it. Reservations
 * are keyed by an opaque `(source_type, source_id)` pair that this service never resolves.
 *
 * All quantity math goes through {@see Decimal} (exact decimal strings, scale 4): no native floats.
 */
final readonly class StockReservationService
{
    private const int LOCK_SECONDS = 10;

    private const int LOCK_WAIT_SECONDS = 5;

    /**
     * Quantity of the item that can still be reserved for the company: the on-hand quantity across
     * all warehouses minus the live reservations.
     */
    public function available(int $companyId, int $itemId): string
    {
        $on_hand = $this->decimalFromAggregate(
            StockLevel::query()
                ->where('company_id', $companyId)
                ->where('item_id', $itemId)
                ->sum('quantity'),
        );

        $reserved = $this->decimalFromAggregate(
            $this->liveReservations($companyId, $itemId)->sum('quantity'),
        );

        return Decimal::sub($on_hand, $reserved);
    }

    /**
     * Holds `$quantity` of the item for the source. The availability check and the insert run in one
     * critical section guarded twice: a per-(company, item) atomic cache lock is a cheap first gate,
     * and inside the transaction the item's stock rows are row-locked (`FOR UPDATE`) before
     * availability is recomputed. The row lock is the real guarantee: the database holds it until the
     * actual COMMIT, so it still serializes reservers when the caller runs `reserve()` inside its own
     * outer transaction (where the inner transaction is only a savepoint and the cache lock is long
     * released by the time the row becomes visible).
     *
     * A `soft` reservation carries `$expiresAt` (null means it does not lapse); a `hard` one never
     * expires and ignores it.
     *
     * @throws InsufficientStockException When `$quantity` exceeds the available quantity.
     * @throws ValidationException When the quantity is not positive or the item/warehouse is not the company's.
     * @throws InvalidArgumentException When `$mode` is not `soft` or `hard`.
     * @throws LockTimeoutException When the item lock cannot be acquired in time.
     */
    public function reserve(
        int $companyId,
        int $itemId,
        string $quantity,
        StockReservationState $mode,
        string $sourceType,
        int $sourceId,
        ?int $warehouseId = null,
        ?CarbonInterface $expiresAt = null,
    ): StockReservation {
        if ($mode !== StockReservationState::Soft && $mode !== StockReservationState::Hard) {
            throw new InvalidArgumentException('A reservation can only be created as soft or hard.');
        }

        $quantity = $this->positiveQuantity($quantity);
        $this->assertItemAndWarehouseBelongToCompany($companyId, $itemId, $warehouseId);

        $lock = Cache::lock("erp:stock-reservation:{$companyId}:{$itemId}", self::LOCK_SECONDS);

        return $lock->block(
            self::LOCK_WAIT_SECONDS,
            fn (): StockReservation => $this->connection()->transaction(function () use ($companyId, $itemId, $quantity, $mode, $sourceType, $sourceId, $warehouseId, $expiresAt): StockReservation {
                $available = $this->availableUnderRowLock($companyId, $itemId);

                if (Decimal::isNegative(Decimal::sub($available, $quantity))) {
                    throw InsufficientStockException::forReservation($companyId, $itemId, $quantity, $available);
                }

                return StockReservation::query()->create([
                    'company_id' => $companyId,
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'quantity' => $quantity,
                    'state' => $mode,
                    'expires_at' => $mode === StockReservationState::Soft ? $expiresAt : null,
                ]);
            }),
        );
    }

    /**
     * Turns every live soft reservation of the source into a hard one and clears its expiry. A soft
     * that is missing or already expired is left alone: the caller reserves a fresh hard one, which
     * re-validates availability.
     */
    public function promoteToHard(string $sourceType, int $sourceId, int $companyId): void
    {
        StockReservation::query()
            ->where('company_id', $companyId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('state', StockReservationState::Soft->value)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->update([
                'state' => StockReservationState::Hard->value,
                'expires_at' => null,
            ]);
    }

    /**
     * The quantity the source still holds as a live `hard` reservation: the sum of its `hard` rows.
     *
     * Only `hard` rows count. A `soft` hold is not yet committed, and `consumed`/`released` rows are
     * terminal; so this is exactly the quantity that {@see consume()} can close and that the
     * evasion/amend lifecycle clamps against before shipping or reducing a line. Company scoping is
     * left to the global {@see BelongsToCompanyScope}: callers run in company context, and the
     * `(source_type, source_id)` pair already pins the figure to one line.
     */
    public function reservedQuantity(string $sourceType, int $sourceId): string
    {
        return $this->sumDecimals(
            StockReservation::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('state', StockReservationState::Hard->value)
                ->pluck('quantity'),
        );
    }

    /**
     * Gives back everything the source still holds. Idempotent: no live rows means nothing to do.
     */
    public function release(string $sourceType, int $sourceId): void
    {
        StockReservation::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->active()
            ->update(['state' => StockReservationState::Released->value]);
    }

    /**
     * Closes `$quantity` of the source's hard reservations (oldest first), splitting the last row
     * when it is only partly consumed. A soft hold must be promoted before it can be consumed.
     *
     * Consuming does not move on-hand stock. A caller that records the outbound movement should do
     * both in one transaction, so availability never briefly counts the same units twice.
     *
     * @throws ValidationException When the quantity is not positive or exceeds the source's hard total.
     */
    public function consume(string $sourceType, int $sourceId, string $quantity): void
    {
        $quantity = $this->positiveQuantity($quantity);

        $this->connection()->transaction(function () use ($sourceType, $sourceId, $quantity): void {
            $rows = StockReservation::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('state', StockReservationState::Hard->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $reserved = '0.0000';

            foreach ($rows as $row) {
                $reserved = Decimal::add($reserved, $row->quantity);
            }

            if (Decimal::isNegative(Decimal::sub($reserved, $quantity))) {
                throw ValidationException::withMessages([
                    'quantity' => ['Cannot consume more than the reserved quantity.'],
                ]);
            }

            $remaining = $quantity;

            foreach ($rows as $row) {
                if (Decimal::isZero($remaining)) {
                    break;
                }

                if (Decimal::isNegative(Decimal::sub($remaining, $row->quantity))) {
                    StockReservation::query()->create([
                        'company_id' => $row->company_id,
                        'item_id' => $row->item_id,
                        'warehouse_id' => $row->warehouse_id,
                        'source_type' => $row->source_type,
                        'source_id' => $row->source_id,
                        'quantity' => $remaining,
                        'state' => StockReservationState::Consumed,
                        'expires_at' => null,
                    ]);

                    $row->quantity = Decimal::sub($row->quantity, $remaining);
                    $row->save();

                    return;
                }

                $remaining = Decimal::sub($remaining, $row->quantity);
                $row->state = StockReservationState::Consumed;
                $row->save();
            }
        });
    }

    /**
     * Availability as seen by a reserver that owns the item's critical section. Locks the stock rows
     * first (in id order, so concurrent reservers cannot deadlock on each other) and then reads the
     * live reservations with a locking read too: a locking read is a current read, so the figures
     * reflect everything committed up to now even on engines whose plain SELECT reads an older
     * snapshot inside a long-running outer transaction. A locking read cannot use SUM() on every
     * engine, so the rows are summed here with exact decimals.
     *
     * Must run inside a transaction, otherwise the row locks are released immediately.
     */
    private function availableUnderRowLock(int $companyId, int $itemId): string
    {
        $on_hand = $this->sumDecimals(
            StockLevel::query()
                ->where('company_id', $companyId)
                ->where('item_id', $itemId)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('quantity'),
        );

        $reserved = $this->sumDecimals(
            $this->liveReservations($companyId, $itemId)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('quantity'),
        );

        return Decimal::sub($on_hand, $reserved);
    }

    /**
     * @param  iterable<mixed>  $quantities
     */
    private function sumDecimals(iterable $quantities): string
    {
        $total = '0.0000';

        foreach ($quantities as $quantity) {
            $total = Decimal::add($total, $this->decimalFromAggregate($quantity));
        }

        return $total;
    }

    /**
     * Reservations that currently hold quantity back: hard ones, and soft ones that have not lapsed.
     *
     * @return Builder<StockReservation>
     */
    private function liveReservations(int $companyId, int $itemId): Builder
    {
        return StockReservation::query()
            ->where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where(function (Builder $query): void {
                $query->where('state', StockReservationState::Hard->value)
                    ->orWhere(function (Builder $soft): void {
                        $soft->where('state', StockReservationState::Soft->value)
                            ->where(function (Builder $unexpired): void {
                                $unexpired->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            });
                    });
            });
    }

    /**
     * @throws ValidationException
     */
    private function positiveQuantity(string $quantity): string
    {
        try {
            $normalized = Decimal::format($quantity);
        } catch (MathException) {
            $normalized = null;
        }

        if ($normalized === null || Decimal::isZero($normalized) || Decimal::isNegative($normalized)) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        return $normalized;
    }

    private function assertItemAndWarehouseBelongToCompany(int $companyId, int $itemId, ?int $warehouseId): void
    {
        $item_exists = Item::query()->whereKey($itemId)->where('company_id', $companyId)->exists();

        if (! $item_exists) {
            throw ValidationException::withMessages([
                'item_id' => ['Item not found for this company.'],
            ]);
        }

        if ($warehouseId === null) {
            return;
        }

        $warehouse_exists = Warehouse::query()->whereKey($warehouseId)->where('company_id', $companyId)->exists();

        if (! $warehouse_exists) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Warehouse not found for this company.'],
            ]);
        }
    }

    /**
     * A SUM comes back as an exact string on MySQL/PostgreSQL but as an int/float on SQLite, so it is
     * only re-expressed as a scale-4 decimal here; the arithmetic itself stays exact.
     */
    private function decimalFromAggregate(mixed $sum): string
    {
        return Decimal::format(is_numeric($sum) ? (string) $sum : '0');
    }

    private function connection(): ConnectionInterface
    {
        return new StockReservation()->getConnection();
    }
}
