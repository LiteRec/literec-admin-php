<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\InMemory;

use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;

/**
 * In-memory adapter for the {@see Households} port. Used by domain and
 * application unit tests so they stay #[Small] and never boot Doctrine,
 * and (until LRA-37 lands a Doctrine adapter) by the DI container to wire
 * application command handlers.
 */
final class InMemoryHouseholds implements Households
{
    /** @var array<string, Household> indexed by HouseholdId->value */
    private array $byId = [];

    public function save(Household $household): void
    {
        $this->byId[$household->id()->value] = $household;
    }

    public function findById(HouseholdId $id): Household
    {
        return $this->byId[$id->value] ?? throw HouseholdNotFound::byId($id);
    }

    public function findByMemberId(MemberId $id): Household
    {
        foreach ($this->byId as $household) {
            foreach ($household->members() as $member) {
                if ($member->id()->equals($id)) {
                    return $household;
                }
            }
        }

        throw HouseholdNotFound::byMemberId($id);
    }

    public function findByMemberCode(MemberCode $code): Household
    {
        foreach ($this->byId as $household) {
            foreach ($household->members() as $member) {
                if ($member->code()->equals($code)) {
                    return $household;
                }
            }
        }

        throw HouseholdNotFound::byMemberCode($code);
    }

    /**
     * No real locking in-memory (single-process test double); provides the
     * same not-found/already-merged semantics as the Doctrine adapter so
     * unit tests can exercise {@see \App\Households\Application\Command\MergeMembersHandler}
     * without booting Doctrine.
     */
    public function lockUnmergedMember(HouseholdId $householdId, MemberId $memberId): void
    {
        $household = $this->byId[$householdId->value] ?? null;

        if ($household === null) {
            throw MemberNotFound::inHousehold($householdId, $memberId);
        }

        foreach ($household->members() as $member) {
            if (!$member->id()->equals($memberId)) {
                continue;
            }

            if ($member->lifecycle()->isMerged()) {
                throw MemberAlreadyMerged::for($memberId);
            }

            return;
        }

        throw MemberNotFound::inHousehold($householdId, $memberId);
    }
}
