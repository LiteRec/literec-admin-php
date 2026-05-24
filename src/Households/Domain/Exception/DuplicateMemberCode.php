<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\MemberCode;
use DomainException;

final class DuplicateMemberCode extends DomainException implements HouseholdsDomainException
{
    /**
     * @param MemberCode $code Not embedded in the message — member codes are
     *                         caller-controlled identifiers that should not
     *                         leak into logs. The parameter is kept on the
     *                         signature so call sites stay readable and for
     *                         future structured-context support.
     */
    public static function for(MemberCode $code): self
    {
        unset($code);

        return new self('A member with this code already exists in the household.');
    }
}
