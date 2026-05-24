<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use DomainException;

final class MemberNotFound extends DomainException implements HouseholdsDomainException
{
    /**
     * @param HouseholdId $householdId Not embedded in the message — see the
     *                                 codebase-wide convention of keeping
     *                                 caller-controlled identifiers out of
     *                                 exception text.
     * @param MemberId    $memberId    Same — not embedded in the message.
     */
    public static function inHousehold(HouseholdId $householdId, MemberId $memberId): self
    {
        unset($householdId, $memberId);

        return new self('The household has no member with the supplied id.');
    }
}
