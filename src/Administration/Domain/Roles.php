<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\Exception\ConcurrentRoleModification;
use App\Administration\Domain\Exception\DuplicateRoleName;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;

/**
 * Domain port for persisting and retrieving Role aggregates.
 *
 * Forbids generic finders (`findBy`, `findOneBy`, `createQueryBuilder`
 * etc.); every accessor is named after a domain question staff/admin
 * users actually ask.
 */
interface Roles
{
    /**
     * @throws DuplicateRoleName when a role with the same name already
     *         exists (caught via the unique constraint on race conditions).
     */
    public function add(Role $role): void;

    /**
     * @throws RoleNotFound when the role does not exist.
     * @throws ConcurrentRoleModification when the save races a concurrent
     *         modification of the same role.
     * @throws DuplicateRoleName when a rename collides with another
     *         role's name (caught via the unique constraint).
     */
    public function save(Role $role): void;

    /**
     * @throws RoleNotFound when no role has this id.
     */
    public function byId(RoleId $id): Role;

    /**
     * @throws RoleNotFound when no role has this name.
     */
    public function byName(RoleName $name): Role;

    public function existsWithName(RoleName $name): bool;

    /**
     * @return list<Role>
     */
    public function allActive(): array;
}
