<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Quick-filter segment for the Users list (LRA-192) filter pills. Distinct
 * from {@see \App\Households\Domain\ValueObject\ResidencyStatus}: a segment
 * is a list-page display grouping, not a domain concept, and `Inactive`
 * has no corresponding residency value at all.
 */
enum MembersSegment: string
{
    case All = 'all';
    case Residents = 'residents';
    case NonResidents = 'nonResidents';
    case Inactive = 'inactive';

    /**
     * Maps an arbitrary request value onto a segment, defaulting to `All`
     * for anything unset or unrecognised so a stray/stale query string
     * never produces an error page.
     */
    public static function fromRequestValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::All;
    }
}
