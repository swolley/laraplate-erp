<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Historical exchange rate from one ISO currency to another.
 *
 * @property string $from_currency
 * @property string $to_currency
 * @property numeric-string $rate
 * @property \Carbon\CarbonInterface $rate_date
 * @property string|null $source
 * @mixin \Eloquent
 * @mixin IdeHelperExchangeRate
 */
final class ExchangeRate extends Model
{
    #[Override]
    protected $table = ERPTables::ExchangeRates->value;

    #[Override]
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'rate_date',
        'source',
    ];

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'rate' => ['required', 'numeric', 'min:0.00000001'],
            'rate_date' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:80'],
        ]);
        $rules['update'] = $rules['create'];

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (ExchangeRate $rate): void {
            $rate->from_currency = strtoupper((string) $rate->from_currency);
            $rate->to_currency = strtoupper((string) $rate->to_currency);
        });
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'rate_date' => 'date',
        ];
    }
}
