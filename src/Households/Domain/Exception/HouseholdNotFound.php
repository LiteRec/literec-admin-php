<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use DomainException;

final class HouseholdNotFound extends DomainException implements HouseholdsDomainException
{
    public static function byId(HouseholdId $id): self
    {
        return new self(sprintf('No household exists with id "%s".', $id->value));
    }

    public static function byMemberId(MemberId $id): self
    {
        return new self(sprintf('No household contains a member with id "%s".', $id->value));
    }

    public static function byMemberCode(MemberCode $code): self
    {
        return new self(sprintf('No household contains a member with code "%s".', $code->value));
    }
}
