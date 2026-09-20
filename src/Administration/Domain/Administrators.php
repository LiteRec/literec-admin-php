<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SignInAccountId;

/**
 * Domain port for persisting and retrieving Administrator aggregates.
 *
 * Forbids generic finders (`findBy`, `findOneBy`, `createQueryBuilder`
 * etc.); every accessor is named after a domain question staff/admin
 * users actually ask.
 */
interface Administrators
{
    /**
     * Persists a newly granted administrator.
     *
     * @throws \App\Administration\Domain\Exception\SignInAccountAlreadyAnAdministrator
     *         when the sign-in account already has an administrator
     *         record (caught via the unique constraint on race
     *         conditions).
     */
    public function add(Administrator $administrator): void;

    /**
     * Persists modifications to an existing administrator.
     *
     * @throws \App\Administration\Domain\Exception\AdministratorNotFound
     *         when the administrator does not exist.
     * @throws \App\Administration\Domain\Exception\ConcurrentAdministratorModification
     *         when the save races a concurrent modification of the same
     *         administrator.
     */
    public function save(Administrator $administrator): void;

    /**
     * @throws \App\Administration\Domain\Exception\AdministratorNotFound
     *         when no administrator has this id.
     */
    public function byId(AdministratorId $id): Administrator;

    /**
     * @throws \App\Administration\Domain\Exception\AdministratorNotFound
     *         when the sign-in account has no administrator record.
     */
    public function forSignInAccount(SignInAccountId $id): Administrator;

    public function existsForSignInAccount(SignInAccountId $id): bool;

    /**
     * Every active administrator with $roleId directly assigned — the
     * "directly assigned holders" side of the "see which administrators
     * a role affects" question. A future read model combines this with
     * {@see Ranks::listGrantingRole()} (the rank-granted side) to answer
     * the full blast-radius question; this port answers only the side
     * that is this aggregate's own data.
     *
     * @return list<Administrator>
     */
    public function activeHoldersOfRole(RoleId $roleId): array;
}
