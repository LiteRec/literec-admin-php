<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\MemberId;
use DomainException;

/**
 * Raised by {@see App\Households\Domain\MemberMergePolicy::assertSurvivorAccepts()}
 * when the chosen survivor is deactivated. The UI hides the merge trigger
 * for a deactivated member's detail page, but that is presentation only —
 * the domain enforces the rule itself so a deep link, stale tab, or direct
 * POST cannot merge a live duplicate into a deactivated record (LRA-208).
 */
final class InactiveSurvivorCannotAcceptMerge extends DomainException implements HouseholdsDomainException
{
    /**
     * @param MemberId $id Not embedded in the message — keep identifiers out
     *                     of exception text to avoid leaking into logs.
     */
    public static function for(MemberId $id): self
    {
        unset($id);

        return new self('A deactivated member cannot be the survivor of a merge.');
    }
}
