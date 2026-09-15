<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\MemberId;
use DomainException;

/**
 * Raised when a mutation targets a member that is already merged into a
 * survivor — either the merge operation itself (either side already
 * merged) or any other {@see App\Households\Domain\Household} mutator
 * called against a merged member (LRA-208).
 */
final class MemberAlreadyMerged extends DomainException implements HouseholdsDomainException
{
    /**
     * @param MemberId $id Not embedded in the message — keep identifiers out
     *                     of exception text to avoid leaking into logs.
     */
    public static function for(MemberId $id): self
    {
        unset($id);

        return new self('This member has already been merged into another member.');
    }
}
