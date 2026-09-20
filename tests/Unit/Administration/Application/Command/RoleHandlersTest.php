<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Application\Command\DefineRole;
use App\Administration\Application\Command\DefineRoleHandler;
use App\Administration\Application\Command\GrantPrivilegeToRole;
use App\Administration\Application\Command\GrantPrivilegeToRoleHandler;
use App\Administration\Application\Command\RenameRole;
use App\Administration\Application\Command\RenameRoleHandler;
use App\Administration\Application\Command\ReplaceRolePrivileges;
use App\Administration\Application\Command\ReplaceRolePrivilegesHandler;
use App\Administration\Application\Command\RetireRole;
use App\Administration\Application\Command\RetireRoleHandler;
use App\Administration\Application\Command\RevokePrivilegeFromRole;
use App\Administration\Application\Command\RevokePrivilegeFromRoleHandler;
use App\Administration\Application\Command\RewordRole;
use App\Administration\Application\Command\RewordRoleHandler;
use App\Administration\Domain\Event\RoleDefined;
use App\Administration\Domain\Event\RolePrivilegeGranted;
use App\Administration\Domain\Event\RolePrivilegeRevoked;
use App\Administration\Domain\Event\RolePrivilegesReplaced;
use App\Administration\Domain\Event\RoleRenamed;
use App\Administration\Domain\Event\RoleRetired;
use App\Administration\Domain\Event\RoleReworded;
use App\Administration\Domain\Exception\DuplicateRoleName;
use App\Administration\Domain\Exception\RoleAlreadyRetired;
use App\Administration\Domain\Exception\UnknownPrivilege;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeLookup;
use App\Administration\Domain\Role;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use App\Administration\Infrastructure\Authorization\CataloguePrivilegeLookup;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryRoles;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SequenceAdministrationIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

#[Small]
final class RoleHandlersTest extends TestCase
{
    private const string ROLE_ID = '019571bf-5d51-7000-b500-00000000ba01';
    private const string UNKNOWN_PRIVILEGE = 'NOT_A_PRIVILEGE';

    private InMemoryRoles $roles;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private ActorAssembler $actors;
    private PrivilegeLookup $privilegeLookup;

    protected function setUp(): void
    {
        $this->roles = new InMemoryRoles();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
        $this->actors = new ActorAssembler();
        // The real catalogue-backed adapter, not a mock: it is stateless
        // and side-effect-free (the NullLogger absorbs its one log call),
        // so exercising it here is more honest than a fake that could
        // drift from Privilege's actual case list.
        $this->privilegeLookup = new CataloguePrivilegeLookup(new NullLogger());
    }

    #[Test]
    #[TestDox('DefineRoleHandler defines a role and dispatches RoleDefined.')]
    public function define_role_handler_defines_and_dispatches(): void
    {
        $handler = new DefineRoleHandler(
            $this->roles,
            new SequenceAdministrationIdentityGenerator([RoleId::fromString(self::ROLE_ID)]),
            $this->clock,
            $this->actors,
            $this->privilegeLookup,
            $this->eventBus,
        );

        $id = $handler(new DefineRole(
            'Front Desk',
            'Front-of-house operations.',
            [Privilege::ViewUsers->value],
            ActorKind::System->value,
        ));

        self::assertSame(self::ROLE_ID, $id->value);
        $role = $this->roles->byId($id);
        self::assertSame('Front Desk', $role->name()->value);
        self::assertTrue($role->privileges()->contains(Privilege::ViewUsers));
        self::assertCount(1, $this->eventBus->dispatchedMessages());
        self::assertInstanceOf(RoleDefined::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('DefineRoleHandler throws DuplicateRoleName when a role already has that name.')]
    public function define_role_handler_rejects_duplicate_name(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk');

        $handler = new DefineRoleHandler(
            $this->roles,
            new SequenceAdministrationIdentityGenerator([RoleId::fromString('019571bf-5d51-7000-b500-00000000ba02')]),
            $this->clock,
            $this->actors,
            $this->privilegeLookup,
            $this->eventBus,
        );

        $this->expectException(DuplicateRoleName::class);
        $handler(new DefineRole('Front Desk', '', [], ActorKind::System->value));
    }

    #[Test]
    #[TestDox('DefineRoleHandler throws UnknownPrivilege for an unrecognised privilege name.')]
    public function define_role_handler_rejects_unknown_privilege(): void
    {
        $handler = new DefineRoleHandler(
            $this->roles,
            new SequenceAdministrationIdentityGenerator([RoleId::fromString(self::ROLE_ID)]),
            $this->clock,
            $this->actors,
            $this->privilegeLookup,
            $this->eventBus,
        );

        $this->expectException(UnknownPrivilege::class);
        $handler(new DefineRole('Front Desk', '', [self::UNKNOWN_PRIVILEGE], ActorKind::System->value));
    }

    #[Test]
    #[TestDox('RenameRoleHandler renames the role and dispatches RoleRenamed.')]
    public function rename_role_handler_renames_and_dispatches(): void
    {
        $this->seedRole(self::ROLE_ID, 'Old Name');
        $handler = new RenameRoleHandler($this->roles, $this->clock, $this->actors, $this->eventBus);

        $handler(new RenameRole(self::ROLE_ID, 'New Name', ActorKind::System->value));

        self::assertSame('New Name', $this->roles->byId(RoleId::fromString(self::ROLE_ID))->name()->value);
        self::assertInstanceOf(RoleRenamed::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('RewordRoleHandler updates the description and dispatches RoleReworded.')]
    public function reword_role_handler_rewords_and_dispatches(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk');
        $handler = new RewordRoleHandler($this->roles, $this->clock, $this->actors, $this->eventBus);

        $handler(new RewordRole(self::ROLE_ID, 'Updated description.', ActorKind::System->value));

        self::assertSame(
            'Updated description.',
            $this->roles->byId(RoleId::fromString(self::ROLE_ID))->description()->value,
        );
        self::assertInstanceOf(RoleReworded::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('GrantPrivilegeToRoleHandler grants the privilege and dispatches RolePrivilegeGranted.')]
    public function grant_privilege_handler_grants_and_dispatches(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk');
        $handler = new GrantPrivilegeToRoleHandler(
            $this->roles,
            $this->clock,
            $this->actors,
            $this->privilegeLookup,
            $this->eventBus,
        );

        $handler(new GrantPrivilegeToRole(self::ROLE_ID, Privilege::ViewUsers->value, ActorKind::System->value));

        self::assertTrue(
            $this->roles->byId(RoleId::fromString(self::ROLE_ID))->privileges()->contains(Privilege::ViewUsers),
        );
        self::assertInstanceOf(RolePrivilegeGranted::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('GrantPrivilegeToRoleHandler throws UnknownPrivilege for an unrecognised privilege name.')]
    public function grant_privilege_handler_rejects_unknown_privilege(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk');
        $handler = new GrantPrivilegeToRoleHandler(
            $this->roles,
            $this->clock,
            $this->actors,
            $this->privilegeLookup,
            $this->eventBus,
        );

        $this->expectException(UnknownPrivilege::class);
        $handler(new GrantPrivilegeToRole(self::ROLE_ID, self::UNKNOWN_PRIVILEGE, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('RevokePrivilegeFromRoleHandler revokes the privilege and dispatches RolePrivilegeRevoked.')]
    public function revoke_privilege_handler_revokes_and_dispatches(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk', PrivilegeSet::of(Privilege::ViewUsers));
        $handler = new RevokePrivilegeFromRoleHandler(
            $this->roles,
            $this->clock,
            $this->actors,
            $this->privilegeLookup,
            $this->eventBus,
        );

        $handler(new RevokePrivilegeFromRole(self::ROLE_ID, Privilege::ViewUsers->value, ActorKind::System->value));

        self::assertFalse(
            $this->roles->byId(RoleId::fromString(self::ROLE_ID))->privileges()->contains(Privilege::ViewUsers),
        );
        self::assertInstanceOf(RolePrivilegeRevoked::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('RevokePrivilegeFromRoleHandler throws UnknownPrivilege for an unrecognised privilege name.')]
    public function revoke_privilege_handler_rejects_unknown_privilege(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk', PrivilegeSet::of(Privilege::ViewUsers));
        $handler = new RevokePrivilegeFromRoleHandler(
            $this->roles,
            $this->clock,
            $this->actors,
            $this->privilegeLookup,
            $this->eventBus,
        );

        $this->expectException(UnknownPrivilege::class);
        $handler(new RevokePrivilegeFromRole(self::ROLE_ID, self::UNKNOWN_PRIVILEGE, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('ReplaceRolePrivilegesHandler replaces the whole bundle and dispatches RolePrivilegesReplaced.')]
    public function replace_privileges_handler_replaces_and_dispatches(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk', PrivilegeSet::of(Privilege::ViewUsers));
        $handler = new ReplaceRolePrivilegesHandler(
            $this->roles,
            $this->clock,
            $this->actors,
            $this->privilegeLookup,
            $this->eventBus,
        );

        $handler(new ReplaceRolePrivileges(
            self::ROLE_ID,
            [Privilege::AddUsers->value, Privilege::EditUsers->value],
            ActorKind::System->value,
        ));

        $role = $this->roles->byId(RoleId::fromString(self::ROLE_ID));
        self::assertFalse($role->privileges()->contains(Privilege::ViewUsers));
        self::assertTrue($role->privileges()->contains(Privilege::AddUsers));
        self::assertTrue($role->privileges()->contains(Privilege::EditUsers));
        self::assertInstanceOf(RolePrivilegesReplaced::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('ReplaceRolePrivilegesHandler throws UnknownPrivilege for an unrecognised privilege name.')]
    public function replace_privileges_handler_rejects_unknown_privilege(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk', PrivilegeSet::of(Privilege::ViewUsers));
        $handler = new ReplaceRolePrivilegesHandler(
            $this->roles,
            $this->clock,
            $this->actors,
            $this->privilegeLookup,
            $this->eventBus,
        );

        $this->expectException(UnknownPrivilege::class);
        $handler(new ReplaceRolePrivileges(self::ROLE_ID, [self::UNKNOWN_PRIVILEGE], ActorKind::System->value));
    }

    #[Test]
    #[TestDox('RetireRoleHandler retires the role and dispatches RoleRetired.')]
    public function retire_role_handler_retires_and_dispatches(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk');
        $handler = new RetireRoleHandler($this->roles, $this->clock, $this->actors, $this->eventBus);

        $handler(new RetireRole(self::ROLE_ID, ActorKind::System->value));

        self::assertTrue($this->roles->byId(RoleId::fromString(self::ROLE_ID))->isRetired());
        self::assertInstanceOf(RoleRetired::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('RetireRoleHandler throws RoleAlreadyRetired when the role is already retired.')]
    public function retire_role_handler_rejects_double_retire(): void
    {
        $this->seedRole(self::ROLE_ID, 'Front Desk');
        $handler = new RetireRoleHandler($this->roles, $this->clock, $this->actors, $this->eventBus);
        $handler(new RetireRole(self::ROLE_ID, ActorKind::System->value));

        $this->expectException(RoleAlreadyRetired::class);
        $handler(new RetireRole(self::ROLE_ID, ActorKind::System->value));
    }

    private function seedRole(string $id, string $name, ?PrivilegeSet $privileges = null): void
    {
        $role = Role::define(
            RoleId::fromString($id),
            RoleName::of($name),
            RoleDescription::empty(),
            $privileges ?? PrivilegeSet::none(),
            Actor::system(),
            $this->clock,
        );
        $role->releaseEvents();
        $this->roles->add($role);
    }
}
