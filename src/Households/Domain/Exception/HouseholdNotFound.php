<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use DomainException;

final class HouseholdNotFound extends DomainException implements HouseholdsDomainException
{
    /**
     * @param HouseholdId $id Not embedded in the message — see the class-
     *                        wide convention of keeping caller-controlled
     *                        identifiers out of exception text.
     */
    public static function byId(HouseholdId $id): self
    {
        unset($id);

        return new self('No household exists with the supplied id.');
    }

    /**
     * @param MemberId $id Not embedded in the message — see the byId() note.
     */
    public static function byMemberId(MemberId $id): self
    {
        unset($id);

        return new self('No household contains a member with the supplied id.');
    }

    /**
     * @param MemberCode $code Not embedded in the message — member codes are
     *                         caller-controlled identifiers that should not
     *                         leak into logs.
     */
    public static function byMemberCode(MemberCode $code): self
    {
        unset($code);

        return new self('No household contains a member with the supplied code.');
    }
}
