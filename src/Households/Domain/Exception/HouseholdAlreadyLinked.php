<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use DomainException;

final class HouseholdAlreadyLinked extends DomainException implements HouseholdsDomainException
{
    /**
     * @param MemberId    $memberId    Not embedded in the message — keep
     *                                 identifiers out of exception text.
     * @param HouseholdId $householdId Same — not embedded in the message.
     */
    public static function for(MemberId $memberId, HouseholdId $householdId): self
    {
        unset($memberId, $householdId);

        return new self('The member is already shared with that household.');
    }

    /**
     * Raised when a unique-constraint violation on
     * `household_member_affiliations`' primary key surfaces at flush time —
     * a second concurrent link request for the same (member, household)
     * pair that both passed the in-memory `isSharedWith()` check before
     * either committed. No identifiers are available at this point (the
     * failure is translated from a raw database exception), so this named
     * constructor takes none, matching {@see self::for()}'s convention of
     * not embedding identifiers in the message.
     */
    public static function detectedOnWrite(): self
    {
        return new self('The member is already shared with that household.');
    }
}
