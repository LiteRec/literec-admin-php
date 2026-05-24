<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidMemberCode;
use Stringable;

/**
 * Human-readable membership code allocated to a HouseholdMember (e.g.
 * "M000001"). Allowed characters: ASCII letters, digits, hyphen, underscore.
 */
final readonly class MemberCode implements Stringable
{
    private const ALLOWED_PATTERN = '/^[A-Za-z0-9_\-]+$/';

    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidMemberCode::empty();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > InvalidMemberCode::MAX_LENGTH) {
            throw InvalidMemberCode::tooLong($length);
        }

        if (preg_match(self::ALLOWED_PATTERN, $trimmed) !== 1) {
            throw InvalidMemberCode::illegalCharacters();
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
