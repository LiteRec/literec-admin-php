<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidHeight;

/**
 * A member's height, stored and entered as a whole number of inches (US
 * customary), matching the legacy `households.height` column so a future
 * data migration is a straight column copy.
 */
final readonly class Height
{
    public int $inches;

    private function __construct(int $inches)
    {
        $this->inches = $inches;
    }

    public static function ofInches(int $inches): self
    {
        if ($inches < 1) {
            throw InvalidHeight::notPositive();
        }

        if ($inches > InvalidHeight::MAX_INCHES) {
            throw InvalidHeight::exceedsMaximum();
        }

        return new self($inches);
    }

    public function feet(): int
    {
        return intdiv($this->inches, 12);
    }

    public function remainingInches(): int
    {
        return $this->inches % 12;
    }

    public function equals(self $other): bool
    {
        return $this->inches === $other->inches;
    }
}
