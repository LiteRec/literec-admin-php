<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRoleName;
use Stringable;

/**
 * A Role's display name (legacy `security_roles.role_name varchar(50)`;
 * widened here to 80 to give the new model headroom over the legacy
 * column, matching the width already used for other display names in
 * this codebase, e.g. {@see \App\Inventory\Domain\ValueObject\ItemGroupName}).
 *
 * Equality is case-insensitive so "Front Desk" and "front desk" collide
 * as the same role name — matching {@see \App\Administration\Domain\Roles::existsWithName()}'s
 * duplicate-name guard, which must not let a case variant slip past it.
 *
 * Implements Stringable, matching {@see \App\Users\Domain\ValueObject\Username}:
 * Doctrine's DQL parameter-type inference for a QueryBuilder comparison
 * against a custom-typed field is not always reliable, and a bound
 * object with no string conversion fails loudly rather than falling
 * back to one.
 */
final readonly class RoleName implements Stringable
{
    public const int MAX_LENGTH = 80;

    public string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidRoleName::empty();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > self::MAX_LENGTH) {
            throw InvalidRoleName::tooLong($length);
        }

        $this->value = $trimmed;
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return mb_strtolower($this->value, 'UTF-8') === mb_strtolower($other->value, 'UTF-8');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
