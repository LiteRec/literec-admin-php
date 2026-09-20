<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Persistence\Read;

use App\Administration\Application\Query\Port\RoleReadModel;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\Role;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the real Doctrine DBAL {@see RoleReadModel} adapter. Seeds
 * through the write-side {@see Roles} repository so the same
 * migration-defined administration_roles table receives the data the
 * read model then queries via raw SQL — CQRS-lite, same pattern as
 * DoctrineMemberReadModelContractTest.
 *
 * DAMA's PHPUnit extension wraps each test in a transaction rolled back
 * at teardown so rows do not leak between tests.
 */
#[Medium]
final class DoctrineRoleReadModelTest extends KernelTestCase
{
    private const string ROLE_A = '019571bf-5d51-7000-b500-00000000bb01';
    private const string ROLE_B = '019571bf-5d51-7000-b500-00000000bb02';

    private MockClock $clock;
    private Roles $roles;
    private RoleReadModel $readModel;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));

        $roles = static::getContainer()->get(Roles::class);
        self::assertInstanceOf(Roles::class, $roles);
        $this->roles = $roles;

        $readModel = static::getContainer()->get(RoleReadModel::class);
        self::assertInstanceOf(RoleReadModel::class, $readModel);
        $this->readModel = $readModel;
    }

    #[Test]
    #[TestDox('listRoles(false) excludes retired roles and orders by name.')]
    public function list_roles_excludes_retired_by_default(): void
    {
        $this->seedRole(self::ROLE_A, 'Zebra Role', PrivilegeSet::of(Privilege::ViewUsers, Privilege::AddUsers));
        $retired = $this->seedRole(self::ROLE_B, 'Alpha Role', PrivilegeSet::none());
        $retired->retire(Actor::system(), $this->clock);
        $retired->releaseEvents();
        $this->roles->save($retired);

        $page = $this->readModel->listRoles(false);

        self::assertCount(1, $page);
        self::assertSame(self::ROLE_A, $page[0]->id);
        self::assertSame('Zebra Role', $page[0]->name);
        self::assertSame(2, $page[0]->privilegeCount);
        self::assertFalse($page[0]->retired);
    }

    #[Test]
    #[TestDox('listRoles(true) includes retired roles.')]
    public function list_roles_includes_retired_when_asked(): void
    {
        $retired = $this->seedRole(self::ROLE_A, 'Retired Role', PrivilegeSet::none());
        $retired->retire(Actor::system(), $this->clock);
        $retired->releaseEvents();
        $this->roles->save($retired);

        $page = $this->readModel->listRoles(true);

        self::assertCount(1, $page);
        self::assertTrue($page[0]->retired);
    }

    #[Test]
    #[TestDox('roleDetail() projects name, description, and privilege display names.')]
    public function role_detail_projects_full_record(): void
    {
        $this->seedRole(
            self::ROLE_A,
            'Front Desk',
            PrivilegeSet::of(Privilege::ViewUsers),
            RoleDescription::of('Front-of-house operations.'),
        );

        $detail = $this->readModel->roleDetail(RoleId::fromString(self::ROLE_A));

        self::assertSame(self::ROLE_A, $detail->id);
        self::assertSame('Front Desk', $detail->name);
        self::assertSame('Front-of-house operations.', $detail->description);
        self::assertCount(1, $detail->privileges);
        self::assertSame(Privilege::ViewUsers->value, $detail->privileges[0]->name);
        self::assertSame(Privilege::ViewUsers->definition()->displayName, $detail->privileges[0]->displayName);
        self::assertFalse($detail->retired);
    }

    #[Test]
    #[TestDox('roleDetail() throws RoleNotFound when no role has that id.')]
    public function role_detail_throws_when_missing(): void
    {
        $this->expectException(RoleNotFound::class);
        $this->readModel->roleDetail(RoleId::fromString(self::ROLE_A));
    }

    private function seedRole(
        string $id,
        string $name,
        PrivilegeSet $privileges,
        ?RoleDescription $description = null,
    ): Role {
        $role = Role::define(
            RoleId::fromString($id),
            RoleName::of($name),
            $description ?? RoleDescription::empty(),
            $privileges,
            Actor::system(),
            $this->clock,
        );
        $role->releaseEvents();
        $this->roles->add($role);

        return $role;
    }
}
