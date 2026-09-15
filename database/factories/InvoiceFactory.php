<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\Party;

/**
 * Unposted by default: `posted_at` null, `reference` null. Posting is a service operation that
 * allocates the number and writes the journal entry, so a factory that produced a posted invoice
 * would be fabricating accounting.
 *
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    /**
     * @var class-string<Invoice>
     */
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            // Resolved after `company_id`, so the party always belongs to the invoice's company.
            'party_id' => fn (array $attributes): int => Party::factory()
                ->customer()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'direction' => InvoiceDirection::Sale->value,
            'invoice_type' => InvoiceType::Invoice->value,
            'reference' => null,
            'currency' => 'EUR',
            'posted_at' => null,
        ];
    }

    public function withLines(int $count = 2): self
    {
        return $this->afterCreating(function (Invoice $invoice) use ($count): void {
            for ($line_no = 1; $line_no <= $count; $line_no++) {
                InvoiceLine::factory()->for($invoice)->create(['line_no' => $line_no]);
            }
        });
    }

    public function purchase(): self
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => InvoiceDirection::Purchase->value,
            'party_id' => fn (array $resolved): int => Party::factory()
                ->supplier()
                ->create(['company_id' => $resolved['company_id']])
                ->id,
        ]);
    }

    public function creditNote(): self
    {
        return $this->state(fn (array $attributes): array => ['invoice_type' => InvoiceType::CreditNote->value]);
    }

    public function numbered(string $reference): self
    {
        return $this->state(fn (array $attributes): array => ['reference' => $reference]);
    }
}
