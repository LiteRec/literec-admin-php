<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRankName;
use Stringable;

/**
 * A Rank's display name (legacy `admin_ranks.rank_name`; Bellevue's 54 rows
 * include one named after an individual staff member and one prefixed with
 * `*` to force it to sort first — both symptoms of seniority never being a
 * first-class value in the old system).
 *
 * Equality is case-sensitive, matching the plain (non-functional) UNIQUE
 * INDEX the migration creates on the `name` column — unlike
 * {@see \App\Administration\Domain\ValueObject\RoleName}, whose
 * case-insensitive equals() is backed by a functional LOWER(name) index.
 * There is no evidence here that two case variants of the same rank name
 * need to collide.
 */
final readonly class RankName implements Stringable
{
    public const int MAX_LENGTH = 45;

    public string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidRankName::empty();
        }

        if (str_starts_with($trimmed, '*')) {
            throw InvalidRankName::leadingAsterisk($trimmed);
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > self::MAX_LENGTH) {
            throw InvalidRankName::tooLong($length);
        }

        $this->value = $trimmed;
    }

    public static function of(string $value): self
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
