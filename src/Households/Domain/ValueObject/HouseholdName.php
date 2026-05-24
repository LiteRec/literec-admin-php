<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidHouseholdName;
use Stringable;

final readonly class HouseholdName implements Stringable
{
    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidHouseholdName::empty();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > InvalidHouseholdName::MAX_LENGTH) {
            throw InvalidHouseholdName::tooLong($length);
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
