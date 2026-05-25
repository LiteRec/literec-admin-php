<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\NegativeCost;
use App\Inventory\Domain\Exception\QuantityOverflow;

/**
 * Total acquisition cost for a quantity of units at a given cost-per-unit.
 *
 * Only constructed via compute() from a CostPerUnit and a Quantity so
 * the multiplication and overflow check live in one place. Stored as
 * integer cents so persistence and arithmetic stay exact.
 */
final readonly class BatchCost
{
    public int $cents;

    private function __construct(int $cents)
    {
        if ($cents < 0) {
            throw NegativeCost::ofCents($cents);
        }

        $this->cents = $cents;
    }

    public static function compute(CostPerUnit $costPerUnit, Quantity $quantity): self
    {
        if ($costPerUnit->cents !== 0 && $quantity->units > intdiv(PHP_INT_MAX, $costPerUnit->cents)) {
            throw QuantityOverflow::multiplying($costPerUnit->cents, $quantity->units);
        }

        return new self($costPerUnit->cents * $quantity->units);
    }

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
