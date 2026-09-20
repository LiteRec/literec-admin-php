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
 * Equality is case-insensitive, matching {@see \App\Administration\Domain\ValueObject\RoleName}:
 * "Director" and "director" collide as the same rank name, backed by a
 * functional UNIQUE INDEX on LOWER(name) rather than a byte-exact one —
 * a rank ladder is operator-facing and ordered for reading, and nothing
 * should stop a case-variant duplicate being created by accident.
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
        return mb_strtolower($this->value, 'UTF-8') === mb_strtolower($other->value, 'UTF-8');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
