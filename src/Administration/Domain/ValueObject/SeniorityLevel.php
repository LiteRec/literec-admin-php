<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidSeniorityLevel;

/**
 * How senior a {@see \App\Administration\Domain\Rank} is, separated from
 * what that rank may do (LRA-267). The ordering is inverted relative to
 * intuition — ascending means *less* senior: 0 is the most senior rank
 * (vendor support in the legacy data, retained here as an ordinary,
 * privilege-free row), 100 the least senior. This class states that rule
 * once and exposes no raw numeric comparison, so every call site reads as
 * a domain sentence instead of a hand-written `<=`/`>=` an operator has to
 * remember is inverted — exactly the free-text `Min=Rank71` /
 * `Rank<=72` comparisons found baked into legacy role descriptions,
 * which this type exists to make unnecessary.
 *
 * Duplicate levels across ranks are legal and expected — legacy data has
 * five ranks at level 22 — so this type carries no uniqueness claim of
 * its own.
 */
final readonly class SeniorityLevel
{
    public const int MIN = 0;
    public const int MAX = 100;

    public int $value;

    private function __construct(int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidSeniorityLevel::outOfRange($value);
        }

        $this->value = $value;
    }

    public static function of(int $value): self
    {
        return new self($value);
    }

    /**
     * True when this level is the same as, or more senior than, $other —
     * i.e. this value is less than or equal to $other's.
     */
    public function isAtLeastAsSeniorAs(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * True when this level is strictly more senior than $other — i.e.
     * this value is strictly less than $other's.
     */
    public function isMoreSeniorThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
