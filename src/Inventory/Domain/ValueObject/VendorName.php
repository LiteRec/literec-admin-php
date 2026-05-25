<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidVendorName;
use Stringable;

/**
 * Human-readable vendor name. Trimmed, 1..100 characters.
 */
final readonly class VendorName implements Stringable
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
            throw InvalidVendorName::empty();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > InvalidVendorName::MAX_LENGTH) {
            throw InvalidVendorName::tooLong($length);
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
