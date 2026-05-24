<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\MemberCode;
use DomainException;

final class DuplicateMemberCode extends DomainException implements HouseholdsDomainException
{
    public static function for(MemberCode $code): self
    {
        return new self(sprintf(
            'Member code "%s" already exists in this household.',
            $code->value,
        ));
    }
}
