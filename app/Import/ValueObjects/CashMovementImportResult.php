<?php

declare(strict_types=1);

namespace Modules\ERP\Import\ValueObjects;

use Modules\ERP\Import\Enums\ImportMutation;

final readonly class CashMovementImportResult
{
    public function __construct(
        public ImportMutation $mutation,
        public int $movementId,
        public ?int $journalEntryId = null,
    ) {}
}
