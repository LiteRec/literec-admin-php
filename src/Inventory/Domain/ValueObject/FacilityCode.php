<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidFacilityCode;
use Stringable;

/**
 * Stable short code identifying a physical facility (location).
 *
 * Used as the partition key for per-facility stock. The canonical form
 * is uppercase ASCII; lowercase input is rejected so equality stays
 * byte-for-byte exact and persistence rows do not collide on case.
 */
final readonly class FacilityCode implements Stringable
{
    private const PATTERN = '/^[A-Z][A-Z0-9_-]{1,15}$/';

    public string $value;

    private function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidFacilityCode::for($value);
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
