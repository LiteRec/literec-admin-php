<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\MemberId;

/**
 * Stateless domain service asserting the survivor-side invariant of a
 * member merge (LRA-208).
 *
 * The survivor and the duplicate belong to two independent
 * {@see Household} aggregates (possibly the same one). {@see Household::mergeMemberInto()}
 * only has access to the duplicate's aggregate, so the survivor-side
 * check — the survivor id resolves to a real, not-already-merged member —
 * lives here rather than becoming an `if` inside the application service.
 */
final class MemberMergePolicy
{
    public function assertSurvivorAccepts(Household $survivorHousehold, MemberId $survivorId): void
    {
        foreach ($survivorHousehold->members() as $member) {
            if (!$member->id()->equals($survivorId)) {
                continue;
            }

            if ($member->isMerged()) {
                throw MemberAlreadyMerged::for($survivorId);
            }

            return;
        }

        throw MemberNotFound::inHousehold($survivorHousehold->id(), $survivorId);
    }
}
