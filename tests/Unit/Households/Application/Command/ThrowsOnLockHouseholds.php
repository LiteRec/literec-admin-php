<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;

/**
 * Test double proving {@see \App\Households\Application\Command\SplitMemberHandler}'s
 * race-condition fix (LRA-209 review follow-up): {@see self::lockUnmergedMember()}
 * always throws, simulating a source merged concurrently between the
 * handler's in-memory check and the write-time lock, and {@see self::save()}
 * records whether it was ever reached so the test can assert the handler
 * never persists the split when the lock fails.
 */
final class ThrowsOnLockHouseholds implements Households
{
    public int $saveCalls = 0;

    public function __construct(private readonly InMemoryHouseholds $inner)
    {
    }

    public function save(Household $household): void
    {
        $this->saveCalls++;
        $this->inner->save($household);
    }

    public function findById(HouseholdId $id): Household
    {
        return $this->inner->findById($id);
    }

    public function findByMemberId(MemberId $id): Household
    {
        return $this->inner->findByMemberId($id);
    }

    public function findByMemberCode(MemberCode $code): Household
    {
        return $this->inner->findByMemberCode($code);
    }

    public function lockUnmergedMember(HouseholdId $householdId, MemberId $memberId): void
    {
        throw MemberAlreadyMerged::for($memberId);
    }
}
