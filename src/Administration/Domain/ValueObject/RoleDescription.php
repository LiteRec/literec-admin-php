<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRoleDescription;

/**
 * A Role's free-text description.
 *
 * Legacy `security_roles.description` is `text NOT NULL` with a null
 * default — a contradiction the new column does not reproduce: the new
 * `administration_roles.description` column is `TEXT NOT NULL DEFAULT
 * ''`, and the absent case is {@see self::empty()}, never null.
 */
final readonly class RoleDescription
{
    public const int MAX_LENGTH = 1000;

    public string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > self::MAX_LENGTH) {
            throw InvalidRoleDescription::tooLong($length);
        }

        $this->value = $trimmed;
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    public static function empty(): self
    {
        return new self('');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
