<?php

declare(strict_types=1);

namespace App\Users\Domain\ValueObject;

use App\Users\Domain\Exception\InvalidHashedPassword;

/**
 * A pre-hashed password stored against a User aggregate.
 *
 * Never holds plaintext: callers hash via a PasswordHasher adapter at the
 * Application boundary and wrap the resulting digest with `fromHash()`.
 */
final readonly class HashedPassword
{
    private const KNOWN_HASH_PREFIX_PATTERN
        = '/^\$(2[axy]|argon2i|argon2id)\$/';

    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromHash(string $value): self
    {
        if ($value === '') {
            throw InvalidHashedPassword::empty();
        }

        if (preg_match(self::KNOWN_HASH_PREFIX_PATTERN, $value) !== 1) {
            throw InvalidHashedPassword::format();
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
