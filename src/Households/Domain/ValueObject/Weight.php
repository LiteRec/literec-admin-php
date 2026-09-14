<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidWeight;

/**
 * A member's weight, stored and entered as a whole number of pounds (US
 * customary), matching the legacy `households.setWeight($pounds)` shape so
 * a future data migration is a straight column copy.
 */
final readonly class Weight
{
    public int $pounds;

    private function __construct(int $pounds)
    {
        $this->pounds = $pounds;
    }

    public static function ofPounds(int $pounds): self
    {
        if ($pounds < 1) {
            throw InvalidWeight::notPositive();
        }

        if ($pounds > InvalidWeight::MAX_POUNDS) {
            throw InvalidWeight::exceedsMaximum();
        }

        return new self($pounds);
    }

    public function equals(self $other): bool
    {
        return $this->pounds === $other->pounds;
    }
}
