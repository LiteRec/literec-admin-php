<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\ValueObject\RoleId;
use DateTimeImmutable;

/**
 * Child entity owned by {@see Administrator}, one row per role directly
 * assigned to that administrator. Identity is the derived composite
 * (administrator, roleId) pair; there is no standalone id column, which
 * makes double-assignment of the same role impossible at the schema
 * level. Same shape as {@see \App\Households\Domain\HouseholdAffiliation}.
 *
 * roleId names a {@see \App\Administration\Domain\Role} — a separate
 * aggregate in this same context — so it is stored as a plain value
 * object column here, never a Doctrine association: this class does not
 * (and must not) hold a reference to the Role aggregate itself.
 *
 * Although the constructor and accessors are technically `public` (PHP has
 * no package-private modifier), this is considered internal to the
 * aggregate: callers in Application or Infrastructure layers must go
 * through {@see Administrator} methods. Direct instantiation outside the
 * aggregate is a programming error.
 */
final class AdministratorRoleAssignment
{
    private Administrator $administrator;
    private RoleId $roleId;
    private DateTimeImmutable $assignedAt;

    /**
     * Internal-to-aggregate constructor. Use
     * {@see Administrator::assignRole()} to create instances.
     */
    public function __construct(Administrator $administrator, RoleId $roleId, DateTimeImmutable $assignedAt)
    {
        $this->administrator = $administrator;
        $this->roleId = $roleId;
        $this->assignedAt = $assignedAt;
    }

    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    public function assignedAt(): DateTimeImmutable
    {
        return $this->assignedAt;
    }
}
