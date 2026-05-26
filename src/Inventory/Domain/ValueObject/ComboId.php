<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidComboId;
use Stringable;

/**
 * UUID v7 identity for a Combo aggregate.
 *
 * Validation uses a regex on the canonical RFC 9562 form so the Domain
 * layer does not import any UUID library — that is an Infrastructure
 * concern (Deptrac enforces the boundary).
 */
final readonly class ComboId implements Stringable
{
    private const UUID_V7_PATTERN
        = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public string $value;

    private function __construct(string $value)
    {
        if (preg_match(self::UUID_V7_PATTERN, $value) !== 1) {
            throw InvalidComboId::for($value);
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
