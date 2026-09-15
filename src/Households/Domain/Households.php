<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;

/**
 * Domain port for persisting and retrieving Household aggregates.
 *
 * Forbids generic finders ({@see https://github.com/doctrine/orm} `findBy`,
 * `findOneBy`, `createQueryBuilder` etc.); every accessor is named after
 * a domain question staff/admin users actually ask.
 */
interface Households
{
    /**
     * Persists a household aggregate (insert or update).
     */
    public function save(Household $household): void;

    /**
     * @throws \App\Households\Domain\Exception\HouseholdNotFound
     */
    public function findById(HouseholdId $id): Household;

    /**
     * @throws \App\Households\Domain\Exception\HouseholdNotFound
     */
    public function findByMemberId(MemberId $id): Household;

    /**
     * @throws \App\Households\Domain\Exception\HouseholdNotFound
     */
    public function findByMemberCode(MemberCode $code): Household;

    /**
     * Locks the member's row for the remainder of the current transaction
     * and re-asserts it is not merged, under that lock (LRA-208).
     *
     * {@see \App\Households\Domain\MemberMergePolicy::assertSurvivorAccepts()} performs the same
     * "survivor is not merged" check, but only as a plain read before the
     * duplicate's aggregate is loaded — two concurrent merges naming each
     * other as survivor could both pass that read and then both commit,
     * producing a merge cycle. Calling this method inside the same
     * transaction as the duplicate's write closes that window: a
     * concurrent writer either blocks until this transaction commits (and
     * then observes the row already merged) or is chosen as the deadlock
     * victim by the database.
     *
     * @throws \App\Households\Domain\Exception\MemberNotFound when no member
     *         with $memberId exists in $householdId
     * @throws \App\Households\Domain\Exception\MemberAlreadyMerged when the
     *         row is already merged by the time the lock is acquired
     */
    public function lockUnmergedMember(HouseholdId $householdId, MemberId $memberId): void;
}
