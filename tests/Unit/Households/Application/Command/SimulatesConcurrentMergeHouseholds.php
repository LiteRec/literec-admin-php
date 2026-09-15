<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use Psr\Clock\ClockInterface;

/**
 * Test double proving {@see \App\Households\Application\Command\MergeMembersHandler}'s
 * race-condition fix (LRA-208): wraps a real {@see InMemoryHouseholds} and,
 * on the first {@see self::findByMemberId()} call (the point in the
 * handler right after the read-only survivor check and right before the
 * write-time {@see Households::lockUnmergedMember()} re-assertion),
 * simulates a second, already-committed merge that made the survivor
 * itself a duplicate — exactly the interleaving the fix closes.
 */
final class SimulatesConcurrentMergeHouseholds implements Households
{
    private bool $interloperApplied = false;

    public function __construct(
        private readonly InMemoryHouseholds $inner,
        private readonly HouseholdId $survivorHouseholdId,
        private readonly MemberId $survivorId,
        private readonly HouseholdId $interloperHouseholdId,
        private readonly MemberId $interloperSurvivorId,
        private readonly ClockInterface $clock,
    ) {
    }

    public function save(Household $household): void
    {
        $this->inner->save($household);
    }

    public function findById(HouseholdId $id): Household
    {
        return $this->inner->findById($id);
    }

    public function findByMemberId(MemberId $id): Household
    {
        if (!$this->interloperApplied) {
            $this->interloperApplied = true;
            $survivorHousehold = $this->inner->findById($this->survivorHouseholdId);
            $survivorHousehold->mergeMemberInto(
                $this->survivorId,
                $this->interloperHouseholdId,
                $this->interloperSurvivorId,
                $this->clock,
            );
            $survivorHousehold->releaseEvents();
            $this->inner->save($survivorHousehold);
        }

        return $this->inner->findByMemberId($id);
    }

    public function findByMemberCode(MemberCode $code): Household
    {
        return $this->inner->findByMemberCode($code);
    }

    public function lockUnmergedMember(HouseholdId $householdId, MemberId $memberId): void
    {
        $this->inner->lockUnmergedMember($householdId, $memberId);
    }
}
