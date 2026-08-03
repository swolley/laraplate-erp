<?php

declare(strict_types=1);

namespace Modules\ERP\Import\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class PostedImportConflict extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly string $sourceKey,
        public readonly string $externalId,
        public readonly int $movementId,
    ) {
        parent::__construct("Cannot update posted movement {$movementId} from {$sourceKey}:{$externalId}.");
    }

    /** @return array{source_key: string, external_id: string, movement_id: int} */
    public function context(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'external_id' => $this->externalId,
            'movement_id' => $this->movementId,
        ];
    }
}
