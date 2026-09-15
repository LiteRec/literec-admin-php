<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\MemberId;
use DomainException;

/**
 * Raised by {@see \App\Households\Domain\Household::splitMember()} when
 * no transaction references were selected — a split is defined by moving
 * at least one transaction to the new member (LRA-209).
 */
final class SplitSelectionEmpty extends DomainException implements HouseholdsDomainException
{
    /**
     * @param MemberId $sourceMemberId Not embedded in the message — keep
     *                                 identifiers out of exception text to
     *                                 avoid leaking into logs.
     */
    public static function forMember(MemberId $sourceMemberId): self
    {
        unset($sourceMemberId);

        return new self('A split requires at least one selected transaction.');
    }
}
