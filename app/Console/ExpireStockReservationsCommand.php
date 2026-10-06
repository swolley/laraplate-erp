<?php

declare(strict_types=1);

namespace Modules\ERP\Console;

use Modules\Core\Overrides\Command;
use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Models\StockReservation;
use Modules\ERP\Scopes\BelongsToCompanyScope;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;

/**
 * Housekeeping sweep: closes soft reservations whose `expires_at` has passed.
 *
 * Availability already ignores an expired soft on read ({@see \Modules\ERP\Services\Inventory\StockReservationService}),
 * so a missed or late run changes no figure; the sweep only keeps the table from filling with holds
 * that no longer hold anything. It runs across every company (the tenant scope is lifted on purpose)
 * and is a single guarded UPDATE, so it cannot clobber a soft that was promoted to hard meanwhile.
 */
final class ExpireStockReservationsCommand extends Command
{
    #[Override]
    protected $signature = 'erp:stock-reservations:expire';

    #[Override]
    protected $description = 'Release soft stock reservations whose expiry has passed <fg=yellow>(💼 Modules\ERP)</fg=yellow>';

    public function handle(): int
    {
        $released = StockReservation::query()
            ->withoutGlobalScope(BelongsToCompanyScope::class)
            ->where('state', StockReservationState::Soft->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['state' => StockReservationState::Released->value]);

        $this->info(sprintf('Released %d expired soft stock reservations.', $released));

        return BaseCommand::SUCCESS;
    }
}
