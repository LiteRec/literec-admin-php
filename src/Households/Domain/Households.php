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
}
