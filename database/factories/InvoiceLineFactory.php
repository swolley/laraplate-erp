<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;

/**
 * @extends Factory<InvoiceLine>
 */
final class InvoiceLineFactory extends Factory
{
    /**
     * @var class-string<InvoiceLine>
     */
    protected $model = InvoiceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'line_no' => 1,
            'description' => mb_ucfirst($this->faker->words(3, true)),
            'quantity' => $this->faker->numberBetween(1, 20),
            'qty_returned' => 0,
            'unit_price' => $this->faker->randomFloat(2, 5, 500),
        ];
    }
}
