<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\MemberId;
use DomainException;

final class CannotMergeMemberIntoItself extends DomainException implements HouseholdsDomainException
{
    /**
     * @param MemberId $id Not embedded in the message — keep identifiers out
     *                     of exception text to avoid leaking into logs.
     */
    public static function for(MemberId $id): self
    {
        unset($id);

        return new self('A member cannot be merged into itself.');
    }
}
