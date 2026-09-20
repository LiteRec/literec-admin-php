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
    private const string ROLE_NAME_FRONT_DESK = 'Front Desk';
    private const string ROLE_NAME_FACILITY_MANAGER = 'Facility Manager';

    abstract protected function roles(): Roles;

    abstract protected function clock(): MockClock;

    /**
     * Adapter-specific hook run between a write and the read that
     * verifies it, so the assertion exercises a genuine round trip
     * rather than Doctrine's identity map handing back the same
     * in-memory object it was given. A no-op for InMemoryRoles, which
     * has no identity map to reset; the Doctrine adapter clears its
     * EntityManager.
     */
    abstract protected function resetPersistenceContext(): void;

    #[Test]
    #[TestDox('add() then byId() round-trips name, description, privileges, and retired.')]
    public function add_then_by_id_round_trips(): void
    {
        $role = $this->seedRole(
            self::ROLE_A,
            self::ROLE_NAME_FRONT_DESK,
            'Front-of-house operations.',
            [Privilege::ViewUsers],
        );
        $this->resetPersistenceContext();

        $loaded = $this->roles()->byId(RoleId::fromString(self::ROLE_A));
        self::assertSame(self::ROLE_NAME_FRONT_DESK, $loaded->name()->value);
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
        $this->seedRole(self::ROLE_A, self::ROLE_NAME_FACILITY_MANAGER, '', []);
        $this->resetPersistenceContext();

        $loaded = $this->roles()->byName(RoleName::of(self::ROLE_NAME_FACILITY_MANAGER));
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
    #[TestDox('byName() is case-insensitive and returns the role in its stored casing.')]
    public function by_name_is_case_insensitive(): void
    {
        $this->seedRole(self::ROLE_A, self::ROLE_NAME_FRONT_DESK, '', []);
        $this->resetPersistenceContext();

        $loaded = $this->roles()->byName(RoleName::of(strtolower(self::ROLE_NAME_FRONT_DESK)));
        self::assertSame(self::ROLE_A, $loaded->id()->value);
        self::assertSame(self::ROLE_NAME_FRONT_DESK, $loaded->name()->value);
    }

    #[Test]
    #[TestDox('existsWithName() reports whether a role with that name has been added.')]
    public function exists_with_name_reports_membership(): void
    {
        $this->seedRole(self::ROLE_A, self::ROLE_NAME_FRONT_DESK, '', []);

        self::assertTrue($this->roles()->existsWithName(RoleName::of(self::ROLE_NAME_FRONT_DESK)));
        self::assertFalse($this->roles()->existsWithName(RoleName::of(self::ROLE_NAME_FACILITY_MANAGER)));
    }

    #[Test]
    #[TestDox('existsWithName() is case-insensitive, matching RoleName::equals().')]
    public function exists_with_name_is_case_insensitive(): void
    {
        $this->seedRole(self::ROLE_A, self::ROLE_NAME_FRONT_DESK, '', []);

        self::assertTrue($this->roles()->existsWithName(RoleName::of(strtoupper(self::ROLE_NAME_FRONT_DESK))));
    }

    #[Test]
    #[TestDox('add() throws DuplicateRoleName when another role already has that name.')]
    public function add_throws_on_duplicate_name(): void
    {
        $this->seedRole(self::ROLE_A, self::ROLE_NAME_FRONT_DESK, '', []);

        $this->expectException(DuplicateRoleName::class);
        $this->addRoleNamed(self::ROLE_B, self::ROLE_NAME_FRONT_DESK);
    }

    #[Test]
    #[TestDox('add() throws DuplicateRoleName for a case variant of an existing name.')]
    public function add_throws_on_case_variant_of_duplicate_name(): void
    {
        $this->seedRole(self::ROLE_A, self::ROLE_NAME_FRONT_DESK, '', []);

        $this->expectException(DuplicateRoleName::class);
        $this->addRoleNamed(self::ROLE_B, strtoupper(self::ROLE_NAME_FRONT_DESK));
    }

    #[Test]
    #[TestDox('save() throws DuplicateRoleName when renaming into a case variant of another role\'s name.')]
    public function save_throws_when_renamed_into_case_variant_of_another_name(): void
    {
        $this->seedRole(self::ROLE_A, self::ROLE_NAME_FRONT_DESK, '', []);
        $this->seedRole(self::ROLE_B, self::ROLE_NAME_FACILITY_MANAGER, '', []);

        $second = $this->roles()->byId(RoleId::fromString(self::ROLE_B));
        $second->rename(RoleName::of(strtoupper(self::ROLE_NAME_FRONT_DESK)), Actor::system(), $this->clock());
        $second->releaseEvents();

        $this->expectException(DuplicateRoleName::class);
        $this->roles()->save($second);
    }

    #[Test]
    #[TestDox('save() persists rename, reword, and privilege mutations across reloads.')]
    public function save_persists_mutations(): void
    {
        $this->seedRole(self::ROLE_A, self::ROLE_NAME_FRONT_DESK, 'Original.', [Privilege::ViewUsers]);

        $loaded = $this->roles()->byId(RoleId::fromString(self::ROLE_A));
        $loaded->rename(RoleName::of('Renamed'), Actor::system(), $this->clock());
        $loaded->reword(RoleDescription::of('Updated.'), Actor::system(), $this->clock());
        $loaded->grant(Privilege::AddUsers, Actor::system(), $this->clock());
        $loaded->releaseEvents();
        $this->roles()->save($loaded);
        $this->resetPersistenceContext();

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
        $this->resetPersistenceContext();

        $active = $this->roles()->allActive();
        $ids = array_map(static fn (Role $role): string => $role->id()->value, $active);

        self::assertSame([self::ROLE_A], $ids);
    }

    /**
     * Deliberately does not call {@see self::resetPersistenceContext()}:
     * some callers (e.g. all_active_excludes_retired_roles()) mutate and
     * save() the returned Role directly, and Doctrine's save() requires
     * the instance it is given to still be managed. Callers that need a
     * genuine round trip reset explicitly, between this call and the
     * verifying read.
     */
    private function addRoleNamed(string $id, string $name): void
    {
        $role = Role::define(
            RoleId::fromString($id),
            RoleName::of($name),
            RoleDescription::empty(),
            PrivilegeSet::none(),
            Actor::system(),
            $this->clock(),
        );
        $role->releaseEvents();
        $this->roles()->add($role);
    }

    /**
     * Deliberately does not call {@see self::resetPersistenceContext()} —
     * see {@see self::addRoleNamed()}.
     *
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
