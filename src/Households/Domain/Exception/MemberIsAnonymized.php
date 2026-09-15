<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\MemberId;
use DomainException;

/**
 * Raised whenever a mutation targets a member that has already been
 * anonymized (LRA-212): re-anonymizing, editing the profile or contact,
 * or reactivating. Anonymization is a one-way transition — unlike
 * {@see MemberAlreadyMerged}'s "already merged" family of guards, there is
 * no operation that ever succeeds against an anonymized member again.
 */
final class MemberIsAnonymized extends DomainException implements HouseholdsDomainException
{
    /**
     * @param MemberId $id Not embedded in the message — keep identifiers out
     *                     of exception text to avoid leaking into logs.
     */
    public static function cannotBeAnonymizedAgain(MemberId $id): self
    {
        unset($id);

        return new self('This member has already been anonymized.');
    }

    /**
     * @param MemberId $id Not embedded in the message — see above.
     */
    public static function cannotBeModified(MemberId $id): self
    {
        unset($id);

        return new self('An anonymized member cannot be modified.');
    }

    /**
     * @param MemberId $id Not embedded in the message — see above.
     */
    public static function cannotBeReactivated(MemberId $id): self
    {
        unset($id);

        return new self('An anonymized member cannot be reactivated.');
    }
}
