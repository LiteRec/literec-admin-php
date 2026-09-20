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
use App\Administration\Application\Query\View\RoleSummaryView;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
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
    private const string ROLE_C = '019571bf-5d51-7000-b500-00000000bb03';

    private MockClock $clock;
    private Roles $roles;
    private RoleReadModel $readModel;
    private Connection $connection;

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

        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
    }

    #[Test]
    #[TestDox('listRoles(false) excludes retired roles.')]
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
    #[TestDox('listRoles() orders active roles by name ascending, independent of insertion order.')]
    public function list_roles_orders_active_roles_by_name_ascending(): void
    {
        // Seeded in reverse alphabetical order, plus a retired role, so
        // the assertion below can only pass if ORDER BY name is real —
        // a single-active-role fixture cannot distinguish an ORDER BY
        // from no ordering at all.
        $this->seedRole(self::ROLE_A, 'Zebra Role', PrivilegeSet::none());
        $this->seedRole(self::ROLE_B, 'Alpha Role', PrivilegeSet::none());
        $retired = $this->seedRole(self::ROLE_C, 'Middle Role', PrivilegeSet::none());
        $retired->retire(Actor::system(), $this->clock);
        $retired->releaseEvents();
        $this->roles->save($retired);

        $page = $this->readModel->listRoles(false);

        self::assertSame(['Alpha Role', 'Zebra Role'], array_map(
            static fn (RoleSummaryView $view): string => $view->name,
            $page,
        ));
    }

    #[Test]
    #[TestDox('listRoles() privilegeCount excludes a stored name that no longer resolves through the catalogue.')]
    public function list_roles_excludes_unresolvable_privilege_names_from_count(): void
    {
        $this->seedRole(self::ROLE_A, 'Front Desk', PrivilegeSet::of(Privilege::ViewUsers));

        // Simulates a retired Privilege enum case: a name that once
        // matched a case but no longer does. Written directly via SQL
        // because PrivilegeSet::of() only accepts real Privilege cases —
        // there is no write-side path that can produce this row shape.
        $this->connection->executeStatement(
            'UPDATE administration_roles SET privileges = :privileges WHERE id = :id',
            ['privileges' => '["VIEW_USERS","RETIRED_PRIVILEGE"]', 'id' => self::ROLE_A],
        );

        $page = $this->readModel->listRoles(false);

        self::assertSame(1, $page[0]->privilegeCount);
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
