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
}
