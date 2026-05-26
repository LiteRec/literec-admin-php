<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidVendorCode;
use Stringable;

/**
 * Stable short code identifying a vendor.
 *
 * The canonical form is uppercase ASCII; lowercase input is rejected so
 * equality stays byte-for-byte exact and persistence rows do not
 * collide on case.
 */
final readonly class VendorCode implements Stringable
{
    public const MAX_LENGTH = 32;

    private const PATTERN = '/^[A-Z0-9][A-Z0-9_-]{0,31}$/';

    public string $value;

    private function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidVendorCode::for($value);
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
