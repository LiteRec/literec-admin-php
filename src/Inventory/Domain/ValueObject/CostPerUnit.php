<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\NegativeCost;

/**
 * Non-negative per-unit acquisition cost stored as integer cents.
 *
 * Inventory currently models a single-currency (USD) deployment; if the
 * application ever supports multiple currencies, a Currency dimension
 * should be added here rather than shared across contexts.
 */
final readonly class CostPerUnit
{
    public int $cents;

    private function __construct(int $cents)
    {
        if ($cents < 0) {
            throw NegativeCost::ofCents($cents);
        }

        $this->cents = $cents;
    }

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
