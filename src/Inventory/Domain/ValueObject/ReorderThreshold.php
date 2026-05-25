<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidReorderThreshold;

/**
 * Per-item per-facility low-stock threshold.
 *
 * A null threshold (the none() sentinel) means low-stock alerts are
 * disabled for this item-facility pair — never compares as breached.
 */
final readonly class ReorderThreshold
{
    public ?int $units;

    private function __construct(?int $units)
    {
        if ($units !== null && $units < 0) {
            throw InvalidReorderThreshold::for($units);
        }

        $this->units = $units;
    }

    public static function ofUnits(int $units): self
    {
        return new self($units);
    }

    public static function none(): self
    {
        return new self(null);
    }

    public function isBreached(Quantity $onHand): bool
    {
        if ($this->units === null) {
            return false;
        }

        return $onHand->units <= $this->units;
    }

    public function isSet(): bool
    {
        return $this->units !== null;
    }

    public function equals(self $other): bool
    {
        return $this->units === $other->units;
    }
}
