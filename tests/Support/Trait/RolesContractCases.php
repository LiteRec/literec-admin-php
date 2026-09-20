<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Administration\Domain\Exception\DuplicateRoleName;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\Role;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see Roles} adapter. The InMemory
 * and Doctrine drivers both pin to this trait so the implementations
 * cannot drift.
 */
trait RolesContractCases
{
    private const string ROLE_A = '019571bf-5d51-7000-b500-00000000ba01';
    private const string ROLE_B = '019571bf-5d51-7000-b500-00000000ba02';

    abstract protected function roles(): Roles;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox('add() then byId() round-trips name, description, privileges, and retired.')]
    public function add_then_by_id_round_trips(): void
    {
        $role = $this->seedRole(self::ROLE_A, 'Front Desk', 'Front-of-house operations.', [Privilege::ViewUsers]);

        $loaded = $this->roles()->byId(RoleId::fromString(self::ROLE_A));
        self::assertSame('Front Desk', $loaded->name()->value);
        self::assertSame('Front-of-house operations.', $loaded->description()->value);
        self::assertTrue($loaded->privileges()->equals($role->privileges()));
        self::assertFalse($loaded->isRetired());
    }

    #[Test]
    #[TestDox('byId() throws RoleNotFound when no role has that id.')]
    public function by_id_throws_when_missing(): void
    {
        $this->expectException(RoleNotFound::class);
        $this->roles()->byId(RoleId::fromString(self::ROLE_A));
    }

    #[Test]
    #[TestDox('byName() returns the matching role.')]
    public function by_name_returns_matching_role(): void
    {
        $this->seedRole(self::ROLE_A, 'Facility Manager', '', []);

        $loaded = $this->roles()->byName(RoleName::of('Facility Manager'));
        self::assertSame(self::ROLE_A, $loaded->id()->value);
    }

    #[Test]
    #[TestDox('byName() throws RoleNotFound when no role matches.')]
    public function by_name_throws_when_missing(): void
    {
        $this->expectException(RoleNotFound::class);
        $this->roles()->byName(RoleName::of('Nonexistent'));
    }

    #[Test]
    #[TestDox('existsWithName() reports whether a role with that name has been added.')]
    public function exists_with_name_reports_membership(): void
    {
        $this->seedRole(self::ROLE_A, 'Front Desk', '', []);

        self::assertTrue($this->roles()->existsWithName(RoleName::of('Front Desk')));
        self::assertFalse($this->roles()->existsWithName(RoleName::of('Facility Manager')));
    }

    #[Test]
    #[TestDox('add() throws DuplicateRoleName when another role already has that name.')]
    public function add_throws_on_duplicate_name(): void
    {
        $this->seedRole(self::ROLE_A, 'Front Desk', '', []);

        $duplicate = Role::define(
            RoleId::fromString(self::ROLE_B),
            RoleName::of('Front Desk'),
            RoleDescription::empty(),
            PrivilegeSet::none(),
            Actor::system(),
            $this->clock(),
        );
        $duplicate->releaseEvents();

        $this->expectException(DuplicateRoleName::class);
        $this->roles()->add($duplicate);
    }

    #[Test]
    #[TestDox('save() persists rename, reword, and privilege mutations across reloads.')]
    public function save_persists_mutations(): void
    {
        $this->seedRole(self::ROLE_A, 'Front Desk', 'Original.', [Privilege::ViewUsers]);

        $loaded = $this->roles()->byId(RoleId::fromString(self::ROLE_A));
        $loaded->rename(RoleName::of('Renamed'), Actor::system(), $this->clock());
        $loaded->reword(RoleDescription::of('Updated.'), Actor::system(), $this->clock());
        $loaded->grant(Privilege::AddUsers, Actor::system(), $this->clock());
        $loaded->releaseEvents();
        $this->roles()->save($loaded);

        $reloaded = $this->roles()->byId(RoleId::fromString(self::ROLE_A));
        self::assertSame('Renamed', $reloaded->name()->value);
        self::assertSame('Updated.', $reloaded->description()->value);
        self::assertTrue($reloaded->privileges()->contains(Privilege::AddUsers));
    }

    #[Test]
    #[TestDox('save() throws RoleNotFound when the role was never added.')]
    public function save_throws_when_not_added(): void
    {
        $role = Role::define(
            RoleId::fromString(self::ROLE_A),
            RoleName::of('Never Added'),
            RoleDescription::empty(),
            PrivilegeSet::none(),
            Actor::system(),
            $this->clock(),
        );
        $role->releaseEvents();

        $this->expectException(RoleNotFound::class);
        $this->roles()->save($role);
    }

    #[Test]
    #[TestDox('allActive() excludes retired roles.')]
    public function all_active_excludes_retired_roles(): void
    {
        $this->seedRole(self::ROLE_A, 'Active Role', '', []);
        $retired = $this->seedRole(self::ROLE_B, 'Retired Role', '', []);
        $retired->retire(Actor::system(), $this->clock());
        $retired->releaseEvents();
        $this->roles()->save($retired);

        $active = $this->roles()->allActive();
        $ids = array_map(static fn (Role $role): string => $role->id()->value, $active);

        self::assertSame([self::ROLE_A], $ids);
    }

    /**
     * @param list<Privilege> $privileges
     */
    private function seedRole(string $id, string $name, string $description, array $privileges): Role
    {
        $role = Role::define(
            RoleId::fromString($id),
            RoleName::of($name),
            RoleDescription::of($description),
            PrivilegeSet::of(...$privileges),
            Actor::system(),
            $this->clock(),
        );
        $role->releaseEvents();
        $this->roles()->add($role);

        return $role;
    }
}
