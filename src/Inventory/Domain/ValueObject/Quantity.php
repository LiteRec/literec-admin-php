<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\QuantityOverflow;
use App\Inventory\Domain\Exception\QuantityWouldGoNegative;

/**
 * Non-negative integer quantity of physical units.
 *
 * Stock counts, batch sizes, transfer amounts, and component ratios are
 * all expressed as Quantity so the rest of the Domain layer never sees a
 * bare int and never has to re-check for negativity.
 */
final readonly class Quantity
{
    public int $units;

    private function __construct(int $units)
    {
        if ($units < 0) {
            throw QuantityWouldGoNegative::subtracting(0, -$units);
        }

        $this->units = $units;
    }

    public static function ofUnits(int $units): self
    {
        return new self($units);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        if ($this->units > PHP_INT_MAX - $other->units) {
            throw QuantityOverflow::adding($this->units, $other->units);
        }

        return new self($this->units + $other->units);
    }

    public function subtract(self $other): self
    {
        if ($other->units > $this->units) {
            throw QuantityWouldGoNegative::subtracting($this->units, $other->units);
        }

        return new self($this->units - $other->units);
    }

    public function isZero(): bool
    {
        return $this->units === 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->units >= $other->units;
    }

    public function equals(self $other): bool
    {
        return $this->units === $other->units;
    }
}
