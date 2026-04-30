<?php

declare(strict_types=1);

namespace Modules\ERP\Data\EInvoice;

/**
 * Neutral document payload produced before sending to a national / provider gateway.
 *
 * @phpstan-type DocumentData array<string, mixed>
 */
final readonly class EInvoicePayload
{
    /**
     * @param  DocumentData  $document  Serializable document body (no provider types).
     */
    public function __construct(
        public array $document,
        public ?string $mimeType = null,
    ) {}
}
