<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\InMemory;

use App\Administration\Domain\Exception\DuplicateRoleName;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Role;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;

/**
 * In-memory adapter for the {@see Roles} port. Used by domain/application
 * unit tests so they stay #[Small] and never boot Doctrine.
 */
final class InMemoryRoles implements Roles
{
    /** @var array<string, Role> indexed by RoleId->value */
    private array $byId = [];

    public function add(Role $role): void
    {
        if ($this->existsWithName($role->name())) {
            throw DuplicateRoleName::of($role->name()->value);
        }

        $this->byId[$role->id()->value] = $role;
    }

    public function save(Role $role): void
    {
        // Mirror DoctrineRoles semantics: save() only persists changes to
        // an already-added aggregate; calling it on an unknown role is a
        // programming error, not a "create if missing" upsert.
        if (! isset($this->byId[$role->id()->value])) {
            throw RoleNotFound::withId($role->id());
        }

        foreach ($this->byId as $existingId => $existing) {
            if ($existingId !== $role->id()->value && $existing->name()->equals($role->name())) {
                throw DuplicateRoleName::of($role->name()->value);
            }
        }

        $this->byId[$role->id()->value] = $role;
    }

    public function byId(RoleId $id): Role
    {
        return $this->byId[$id->value] ?? throw RoleNotFound::withId($id);
    }

    public function byName(RoleName $name): Role
    {
        foreach ($this->byId as $role) {
            if ($role->name()->equals($name)) {
                return $role;
            }
        }

        throw RoleNotFound::withName($name);
    }

    public function existsWithName(RoleName $name): bool
    {
        foreach ($this->byId as $role) {
            if ($role->name()->equals($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Role>
     */
    public function allActive(): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Role $role): bool => ! $role->isRetired(),
        ));
    }
}
