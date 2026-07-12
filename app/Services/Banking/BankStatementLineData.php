<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

final readonly class BankStatementLineData
{
    /**
     * @param  numeric-string  $amount_doc
     * @param  array<string, mixed>  $raw_payload
     */
    public function __construct(
        public string $booked_at,
        public ?string $value_at,
        public ?string $reference,
        public string $description,
        public string $amount_doc,
        public string $currency_doc,
        public array $raw_payload,
    ) {}
}
