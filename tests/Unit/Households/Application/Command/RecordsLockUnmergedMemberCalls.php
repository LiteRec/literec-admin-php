<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;

/**
 * Spy proving {@see \App\Households\Application\Command\SplitMemberHandler}
 * locks and re-asserts the source is not merged, with the source member
 * id, before saving (LRA-209 review follow-up). Wraps a real
 * {@see InMemoryHouseholds} and records every {@see self::lockUnmergedMember()}
 * call without altering its (non-throwing) behaviour.
 */
final class RecordsLockUnmergedMemberCalls implements Households
{
    /** @var list<array{householdId: HouseholdId, memberId: MemberId}> */
    public array $lockCalls = [];

    public function __construct(private readonly InMemoryHouseholds $inner)
    {
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
        return $this->inner->findByMemberId($id);
    }

    public function findByMemberCode(MemberCode $code): Household
    {
        return $this->inner->findByMemberCode($code);
    }

    public function lockUnmergedMember(HouseholdId $householdId, MemberId $memberId): void
    {
        $this->lockCalls[] = ['householdId' => $householdId, 'memberId' => $memberId];
        $this->inner->lockUnmergedMember($householdId, $memberId);
    }
}
