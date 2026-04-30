<?php

declare(strict_types=1);

namespace Modules\ERP\Data\EInvoice;

/**
 * Outcome of a submit call to an e-invoice gateway (transport-agnostic).
 *
 * @phpstan-type RawData array<string, mixed>
 */
final readonly class EInvoiceSubmissionResult
{
    /**
     * @param  RawData  $raw  Optional opaque provider response for auditing.
     */
    public function __construct(
        public string $externalId,
        public bool $success,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
