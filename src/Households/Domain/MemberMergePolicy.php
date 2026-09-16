<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\Exception\InactiveSurvivorCannotAcceptMerge;
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
 * checks — the survivor id resolves to a real, not-already-merged, active
 * member — live here rather than becoming an `if` inside the application
 * service.
 *
 * @throws MemberNotFound when $survivorId does not belong to $survivorHousehold
 * @throws MemberAlreadyMerged when the survivor is itself already merged
 * @throws InactiveSurvivorCannotAcceptMerge when the survivor is deactivated
 */
final class MemberMergePolicy
{
    public function assertSurvivorAccepts(Household $survivorHousehold, MemberId $survivorId): void
    {
        foreach ($survivorHousehold->members() as $member) {
            if (!$member->id()->equals($survivorId)) {
                continue;
            }

            $lifecycle = $member->lifecycle();
            if ($lifecycle->isMerged()) {
                throw MemberAlreadyMerged::for($survivorId);
            }

            if (!$lifecycle->isActive) {
                throw InactiveSurvivorCannotAcceptMerge::for($survivorId);
            }

            return;
        }

        throw MemberNotFound::inHousehold($survivorHousehold->id(), $survivorId);
    }
}
