<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;

/**
 * Read-side port for the Households context.
 *
 * Sits in the Application layer because it is not a domain invariant
 * (no aggregate consistency boundary) but it is a use-case dependency
 * the query handlers inject. The Doctrine adapter queries the same
 * tables as the write side via direct SQL (CQRS-lite); domain/application
 * unit tests use an in-memory adapter that walks aggregate instances.
 */
interface MemberReadModel
{
    public function search(SearchMembersCriteria $criteria): PageOfMembers;

    /**
     * @throws MemberNotFound when the household contains no member with the
     *                        supplied id (either because the household does
     *                        not exist or the member does not belong to it).
     */
    public function memberDetail(HouseholdId $householdId, MemberId $memberId): MemberDetail;
}
