<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\RankName;
use DomainException;

final class InvalidRankName extends DomainException implements AdministrationDomainException
{
    public static function empty(): self
    {
        return new self('Rank name must not be empty.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Rank name is %d characters; the maximum is %d.',
            $length,
            RankName::MAX_LENGTH,
        ));
    }

    /**
     * The legacy sort hack: Bellevue prefixes a rank name with `*` to force
     * it to sort first, because seniority was never a first-class,
     * comparable value in the old system. {@see \App\Administration\Domain\ValueObject\SeniorityLevel}
     * now carries ordering, so the workaround is rejected outright rather
     * than silently accepted.
     */
    public static function leadingAsterisk(string $value): self
    {
        return new self(sprintf(
            'Rank name "%s" may not start with "*" — ordering is carried by seniority, not by name.',
            $value,
        ));
    }
}
