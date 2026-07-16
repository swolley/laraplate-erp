<?php

declare(strict_types=1);

namespace Modules\ERP\ValueObjects;

use InvalidArgumentException;
use Modules\ERP\Support\Decimal;

final readonly class Money
{
    private function __construct(
        public string $amount,
        public string $currency,
    ) {}

    public static function of(string|float|int $amount, string $currency): self
    {
        $currency = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Money currency must be a 3-letter ISO code.');
        }

        return new self(Decimal::format((string) $amount), $currency);
    }

    public static function zero(string $currency): self
    {
        return self::of('0', $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::of(Decimal::add($this->amount, $other->amount), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::of(Decimal::sub($this->amount, $other->amount), $this->currency);
    }

    public function multiply(string|float|int $multiplier): self
    {
        return self::of(Decimal::mul($this->amount, (string) $multiplier), $this->currency);
    }

    public function negate(): self
    {
        return self::of(Decimal::negate($this->amount), $this->currency);
    }

    public function abs(): self
    {
        return self::of(Decimal::abs($this->amount), $this->currency);
    }

    public function isZero(): bool
    {
        return Decimal::isZero($this->amount);
    }

    public function isNegative(): bool
    {
        return Decimal::isNegative($this->amount);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    /**
     * @return list<self>
     */
    public function allocate(int $parts): array
    {
        if ($parts <= 0) {
            throw new InvalidArgumentException('Money allocation parts must be greater than zero.');
        }

        $base = Decimal::div($this->amount, (string) $parts);
        $allocations = array_fill(0, $parts, self::of($base, $this->currency));
        $allocated = self::zero($this->currency);

        foreach ($allocations as $allocation) {
            $allocated = $allocated->add($allocation);
        }

        $remainder = $this->subtract($allocated);

        if (! $remainder->isZero()) {
            $allocations[0] = $allocations[0]->add($remainder);
        }

        return $allocations;
    }

    public function __toString(): string
    {
        return $this->amount . ' ' . $this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money currency mismatch.');
        }
    }
}
