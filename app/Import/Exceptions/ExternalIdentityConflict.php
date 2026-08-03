<?php

declare(strict_types=1);

namespace Modules\ERP\Import\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class ExternalIdentityConflict extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly string $sourceKey,
        public readonly string $externalId,
        public readonly int $registeredMovementId,
        public readonly int $receivedMovementId,
    ) {
        parent::__construct(
            "External identity {$sourceKey}:{$externalId} belongs to movement {$registeredMovementId}, not {$receivedMovementId}.",
        );
    }

    /** @return array{source_key: string, external_id: string, registered_movement_id: int, received_movement_id: int} */
    public function context(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'external_id' => $this->externalId,
            'registered_movement_id' => $this->registeredMovementId,
            'received_movement_id' => $this->receivedMovementId,
        ];
    }
}
