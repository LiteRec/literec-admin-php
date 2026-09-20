<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Application\Command\AssignRoleToAdministrator;
use App\Administration\Application\Command\AssignRoleToAdministratorHandler;
use App\Administration\Application\Command\ChangeAdministratorRank;
use App\Administration\Application\Command\ChangeAdministratorRankHandler;
use App\Administration\Application\Command\GrantAdministrator;
use App\Administration\Application\Command\GrantAdministratorHandler;
use App\Administration\Application\Command\RegrantAdministrator;
use App\Administration\Application\Command\RegrantAdministratorHandler;
use App\Administration\Application\Command\RevokeAdministrator;
use App\Administration\Application\Command\RevokeAdministratorHandler;
use App\Administration\Application\Command\UnassignRoleFromAdministrator;
use App\Administration\Application\Command\UnassignRoleFromAdministratorHandler;
use App\Administration\Domain\Event\AdministratorGranted;
use App\Administration\Domain\Event\AdministratorRankChanged;
use App\Administration\Domain\Event\AdministratorRegranted;
use App\Administration\Domain\Event\AdministratorRevoked;
use App\Administration\Domain\Event\RoleAssignedToAdministrator;
use App\Administration\Domain\Event\RoleUnassignedFromAdministrator;
use App\Administration\Domain\Exception\AdministratorAlreadyActive;
use App\Administration\Domain\Exception\AdministratorAlreadyRevoked;
use App\Administration\Domain\Exception\InvalidActorState;
use App\Administration\Domain\Exception\RankIsRetired;
use App\Administration\Domain\Exception\RankNotFound;
use App\Administration\Domain\Exception\RoleIsRetired;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Exception\SignInAccountAlreadyAnAdministrator;
use App\Administration\Domain\Administrator;
use App\Administration\Domain\Rank;
use App\Administration\Domain\Role;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use App\Administration\Domain\ValueObject\SignInAccountId;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryAdministrators;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryRanks;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryRoles;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SaveCountingAdministrators;
use App\Tests\Support\Fake\SequenceAdministrationIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class AdministratorLifecycleHandlersTest extends TestCase
{
    private const string ADMINISTRATOR_ID = '019571bf-5d51-7000-b500-00000000af10';
    private const string TENURE_ID = '019571bf-5d51-7000-b500-00000000af11';
    private const string SIGN_IN_ACCOUNT_ID = '019571bf-5d51-7000-b500-00000000af12';
    private const string RANK_ID = '019571bf-5d51-7000-b500-00000000af13';
    private const string OTHER_RANK_ID = '019571bf-5d51-7000-b500-00000000af14';
    private const string ROLE_ID = '019571bf-5d51-7000-b500-00000000af15';

    private InMemoryAdministrators $administrators;
    private SaveCountingAdministrators $countingAdministrators;
    private InMemoryRanks $ranks;
    private InMemoryRoles $roles;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private ActorAssembler $actors;

    protected function setUp(): void
    {
        $this->administrators = new InMemoryAdministrators();
        // Every handler is wired against this decorator rather than
        // $this->administrators directly — see SaveCountingRanks'
        // docblock for why an assertion against the stored aggregate
        // alone cannot prove save() was actually called.
        $this->countingAdministrators = new SaveCountingAdministrators($this->administrators);
        $this->ranks = new InMemoryRanks();
        $this->roles = new InMemoryRoles();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
        $this->actors = new ActorAssembler();
    }

    #[Test]
    #[TestDox('GrantAdministratorHandler grants staff status and dispatches AdministratorGranted.')]
    public function grant_administrator_handler_grants_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $handler = new GrantAdministratorHandler(
            $this->countingAdministrators,
            $this->ranks,
            new SequenceAdministrationIdentityGenerator(
                administratorIds: [AdministratorId::fromString(self::ADMINISTRATOR_ID)],
                tenureIds: [AdministratorTenureId::fromString(self::TENURE_ID)],
            ),
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $id = $handler(new GrantAdministrator(self::SIGN_IN_ACCOUNT_ID, self::RANK_ID, ActorKind::System->value));

        self::assertSame(self::ADMINISTRATOR_ID, $id->value);
        self::assertSame(1, $this->countingAdministrators->addCalls);
        $administrator = $this->administrators->byId($id);
        self::assertSame(self::SIGN_IN_ACCOUNT_ID, $administrator->signInAccountId()->value);
        self::assertSame(self::RANK_ID, $administrator->rankId()->value);
        self::assertInstanceOf(AdministratorGranted::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('GrantAdministratorHandler throws RankNotFound when the rank does not exist, without granting.')]
    public function grant_administrator_handler_rejects_unknown_rank(): void
    {
        $handler = new GrantAdministratorHandler(
            $this->countingAdministrators,
            $this->ranks,
            new SequenceAdministrationIdentityGenerator(
                administratorIds: [AdministratorId::fromString(self::ADMINISTRATOR_ID)],
                tenureIds: [AdministratorTenureId::fromString(self::TENURE_ID)],
            ),
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(RankNotFound::class);
        $handler(new GrantAdministrator(self::SIGN_IN_ACCOUNT_ID, self::RANK_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('GrantAdministratorHandler throws RankIsRetired when the rank has been retired, without granting.')]
    public function grant_administrator_handler_rejects_retired_rank(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20, retired: true);
        $handler = new GrantAdministratorHandler(
            $this->countingAdministrators,
            $this->ranks,
            new SequenceAdministrationIdentityGenerator(
                administratorIds: [AdministratorId::fromString(self::ADMINISTRATOR_ID)],
                tenureIds: [AdministratorTenureId::fromString(self::TENURE_ID)],
            ),
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(RankIsRetired::class);
        $handler(new GrantAdministrator(self::SIGN_IN_ACCOUNT_ID, self::RANK_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('GrantAdministratorHandler throws SignInAccountAlreadyAnAdministrator for a second grant.')]
    public function grant_administrator_handler_rejects_duplicate_sign_in_account(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();

        $handler = new GrantAdministratorHandler(
            $this->countingAdministrators,
            $this->ranks,
            new SequenceAdministrationIdentityGenerator(
                administratorIds: [AdministratorId::fromString('019571bf-5d51-7000-b500-00000000af99')],
                tenureIds: [AdministratorTenureId::fromString('019571bf-5d51-7000-b500-00000000af98')],
            ),
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(SignInAccountAlreadyAnAdministrator::class);
        $handler(new GrantAdministrator(self::SIGN_IN_ACCOUNT_ID, self::RANK_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('RevokeAdministratorHandler revokes, calls save(), and dispatches AdministratorRevoked.')]
    public function revoke_administrator_handler_revokes_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();
        $handler = new RevokeAdministratorHandler(
            $this->countingAdministrators,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $handler(new RevokeAdministrator(self::ADMINISTRATOR_ID, 'Left the organization.', ActorKind::System->value));

        self::assertSame(1, $this->countingAdministrators->saveCalls);
        self::assertFalse($this->administrators->byId(AdministratorId::fromString(self::ADMINISTRATOR_ID))->isActive());
        self::assertInstanceOf(AdministratorRevoked::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('RevokeAdministratorHandler throws AdministratorAlreadyRevoked on a second revoke.')]
    public function revoke_administrator_handler_rejects_double_revoke(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();
        $handler = new RevokeAdministratorHandler(
            $this->countingAdministrators,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );
        $handler(new RevokeAdministrator(self::ADMINISTRATOR_ID, 'Left.', ActorKind::System->value));

        $this->expectException(AdministratorAlreadyRevoked::class);
        $handler(new RevokeAdministrator(self::ADMINISTRATOR_ID, 'Left again.', ActorKind::System->value));
    }

    #[Test]
    #[TestDox('RegrantAdministratorHandler reopens a tenure, calls save(), and dispatches AdministratorRegranted.')]
    public function regrant_administrator_handler_regrants_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();
        $revokeHandler = new RevokeAdministratorHandler(
            $this->countingAdministrators,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );
        $revokeHandler(new RevokeAdministrator(self::ADMINISTRATOR_ID, 'Left.', ActorKind::System->value));

        $handler = new RegrantAdministratorHandler(
            $this->countingAdministrators,
            new SequenceAdministrationIdentityGenerator(
                tenureIds: [AdministratorTenureId::fromString('019571bf-5d51-7000-b500-00000000af20')],
            ),
            $this->clock,
            $this->actors,
            $this->eventBus,
        );
        $handler(new RegrantAdministrator(self::ADMINISTRATOR_ID, ActorKind::System->value));

        self::assertSame(2, $this->countingAdministrators->saveCalls);
        self::assertTrue($this->administrators->byId(AdministratorId::fromString(self::ADMINISTRATOR_ID))->isActive());
        self::assertInstanceOf(AdministratorRegranted::class, $this->eventBus->dispatchedMessages()[1]);
    }

    #[Test]
    #[TestDox('RegrantAdministratorHandler throws AdministratorAlreadyActive when the administrator is not revoked.')]
    public function regrant_administrator_handler_rejects_already_active(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();
        $handler = new RegrantAdministratorHandler(
            $this->countingAdministrators,
            new SequenceAdministrationIdentityGenerator(
                tenureIds: [AdministratorTenureId::fromString('019571bf-5d51-7000-b500-00000000af21')],
            ),
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(AdministratorAlreadyActive::class);
        $handler(new RegrantAdministrator(self::ADMINISTRATOR_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('ChangeAdministratorRankHandler changes the rank, saves, and dispatches AdministratorRankChanged.')]
    public function change_administrator_rank_handler_changes_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedRank(self::OTHER_RANK_ID, 'Manager', 30);
        $this->seedAdministrator();
        $handler = new ChangeAdministratorRankHandler(
            $this->countingAdministrators,
            $this->ranks,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $handler(new ChangeAdministratorRank(self::ADMINISTRATOR_ID, self::OTHER_RANK_ID, ActorKind::System->value));

        self::assertSame(1, $this->countingAdministrators->saveCalls);
        self::assertSame(
            self::OTHER_RANK_ID,
            $this->administrators->byId(AdministratorId::fromString(self::ADMINISTRATOR_ID))->rankId()->value,
        );
        self::assertInstanceOf(AdministratorRankChanged::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('ChangeAdministratorRankHandler throws RankNotFound when the new rank does not exist.')]
    public function change_administrator_rank_handler_rejects_unknown_rank(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();
        $handler = new ChangeAdministratorRankHandler(
            $this->countingAdministrators,
            $this->ranks,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(RankNotFound::class);
        $handler(new ChangeAdministratorRank(self::ADMINISTRATOR_ID, self::OTHER_RANK_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('ChangeAdministratorRankHandler throws RankIsRetired when the new rank has been retired.')]
    public function change_administrator_rank_handler_rejects_retired_rank(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedRank(self::OTHER_RANK_ID, 'Manager', 30, retired: true);
        $this->seedAdministrator();
        $handler = new ChangeAdministratorRankHandler(
            $this->countingAdministrators,
            $this->ranks,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(RankIsRetired::class);
        $handler(new ChangeAdministratorRank(self::ADMINISTRATOR_ID, self::OTHER_RANK_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('AssignRoleToAdministratorHandler assigns the role, saves, and dispatches RoleAssignedToAdministrator.')]
    public function assign_role_handler_assigns_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();
        $this->seedRole(self::ROLE_ID, 'Front Desk');
        $handler = new AssignRoleToAdministratorHandler(
            $this->countingAdministrators,
            $this->roles,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $handler(new AssignRoleToAdministrator(self::ADMINISTRATOR_ID, self::ROLE_ID, ActorKind::System->value));

        self::assertSame(1, $this->countingAdministrators->saveCalls);
        self::assertTrue(
            $this->administrators->byId(AdministratorId::fromString(self::ADMINISTRATOR_ID))
                ->assignedRoles()->contains(RoleId::fromString(self::ROLE_ID)),
        );
        self::assertInstanceOf(RoleAssignedToAdministrator::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('AssignRoleToAdministratorHandler throws RoleNotFound when the role does not exist, without assigning.')]
    public function assign_role_handler_rejects_unknown_role(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();
        $handler = new AssignRoleToAdministratorHandler(
            $this->countingAdministrators,
            $this->roles,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(RoleNotFound::class);
        $handler(new AssignRoleToAdministrator(self::ADMINISTRATOR_ID, self::ROLE_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('AssignRoleToAdministratorHandler throws RoleIsRetired for a retired role, without assigning.')]
    public function assign_role_handler_rejects_retired_role(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();
        $this->seedRole(self::ROLE_ID, 'Front Desk', retired: true);
        $handler = new AssignRoleToAdministratorHandler(
            $this->countingAdministrators,
            $this->roles,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(RoleIsRetired::class);
        $handler(new AssignRoleToAdministrator(self::ADMINISTRATOR_ID, self::ROLE_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('UnassignRoleFromAdministratorHandler unassigns, saves, and dispatches RoleUnassignedFromAdministrator.')]
    public function unassign_role_handler_unassigns_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $administrator = $this->seedAdministrator();
        $administrator->assignRole(RoleId::fromString(self::ROLE_ID), Actor::system(), $this->clock);
        $administrator->releaseEvents();
        $this->administrators->save($administrator);

        $handler = new UnassignRoleFromAdministratorHandler(
            $this->countingAdministrators,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );
        $handler(new UnassignRoleFromAdministrator(self::ADMINISTRATOR_ID, self::ROLE_ID, ActorKind::System->value));

        self::assertSame(1, $this->countingAdministrators->saveCalls);
        self::assertFalse(
            $this->administrators->byId(AdministratorId::fromString(self::ADMINISTRATOR_ID))
                ->assignedRoles()->contains(RoleId::fromString(self::ROLE_ID)),
        );
        self::assertInstanceOf(RoleUnassignedFromAdministrator::class, $this->eventBus->dispatchedMessages()[0]);
    }

    /**
     * Pins the AC that an inconsistent actor-kind/identifier pair on a
     * command DTO is rejected rather than recorded — exercised here
     * through one representative lifecycle handler;
     * {@see \App\Tests\Unit\Administration\Application\ActorAssemblerTest}
     * covers every combination exhaustively for the shared assembler
     * every write handler in this context (including these six)
     * delegates to.
     */
    #[Test]
    #[TestDox('RevokeAdministratorHandler rejects an inconsistent actor-kind/identifier pair via ActorAssembler.')]
    public function revoke_administrator_handler_rejects_inconsistent_actor(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedAdministrator();
        $handler = new RevokeAdministratorHandler(
            $this->countingAdministrators,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(InvalidActorState::class);
        $handler(new RevokeAdministrator(
            self::ADMINISTRATOR_ID,
            'Left.',
            ActorKind::System->value,
            self::ADMINISTRATOR_ID,
        ));
    }

    private function seedAdministrator(): Administrator
    {
        $administrator = Administrator::grant(
            AdministratorId::fromString(self::ADMINISTRATOR_ID),
            SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_ID),
            RankId::fromString(self::RANK_ID),
            AdministratorTenureId::fromString(self::TENURE_ID),
            Actor::system(),
            $this->clock,
        );
        $administrator->releaseEvents();
        $this->administrators->add($administrator);

        return $administrator;
    }

    private function seedRank(string $id, string $name, int $seniority, bool $retired = false): void
    {
        $rank = Rank::define(
            RankId::fromString($id),
            RankName::of($name),
            SeniorityLevel::of($seniority),
            AssignedRoles::none(),
            Actor::system(),
            $this->clock,
        );

        if ($retired) {
            $rank->retire(Actor::system(), $this->clock);
        }

        $rank->releaseEvents();
        $this->ranks->add($rank);
    }

    private function seedRole(string $id, string $name, bool $retired = false): void
    {
        $role = Role::define(
            RoleId::fromString($id),
            RoleName::of($name),
            RoleDescription::empty(),
            PrivilegeSet::none(),
            Actor::system(),
            $this->clock,
        );

        if ($retired) {
            $role->retire(Actor::system(), $this->clock);
        }

        $role->releaseEvents();
        $this->roles->add($role);
    }
}
