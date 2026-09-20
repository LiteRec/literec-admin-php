<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Application\Command\ChangeRankSeniority;
use App\Administration\Application\Command\ChangeRankSeniorityHandler;
use App\Administration\Application\Command\DefineRank;
use App\Administration\Application\Command\DefineRankHandler;
use App\Administration\Application\Command\GrantRoleToRank;
use App\Administration\Application\Command\GrantRoleToRankHandler;
use App\Administration\Application\Command\ReinstateRank;
use App\Administration\Application\Command\ReinstateRankHandler;
use App\Administration\Application\Command\RenameRank;
use App\Administration\Application\Command\RenameRankHandler;
use App\Administration\Application\Command\RetireRank;
use App\Administration\Application\Command\RetireRankHandler;
use App\Administration\Application\Command\RevokeRoleFromRank;
use App\Administration\Application\Command\RevokeRoleFromRankHandler;
use App\Administration\Domain\Event\RankDefined;
use App\Administration\Domain\Event\RankReinstated;
use App\Administration\Domain\Event\RankRenamed;
use App\Administration\Domain\Event\RankRetired;
use App\Administration\Domain\Event\RankSeniorityChanged;
use App\Administration\Domain\Event\RoleGrantedToRank;
use App\Administration\Domain\Event\RoleRevokedFromRank;
use App\Administration\Domain\Exception\DuplicateRankName;
use App\Administration\Domain\Exception\RankIsRetired;
use App\Administration\Domain\Exception\RoleIsRetired;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Rank;
use App\Administration\Domain\Role;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryRanks;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryRoles;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SaveCountingRanks;
use App\Tests\Support\Fake\SequenceAdministrationIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class RankHandlersTest extends TestCase
{
    private const string RANK_ID = '019571bf-5d51-7000-b500-00000000ba01';
    private const string ROLE_ID = '019571bf-5d51-7000-b500-00000000ba02';

    private InMemoryRanks $ranks;
    private SaveCountingRanks $countingRanks;
    private InMemoryRoles $roles;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private ActorAssembler $actors;

    protected function setUp(): void
    {
        $this->ranks = new InMemoryRanks();
        // Every handler is wired against this decorator rather than
        // $this->ranks directly: InMemoryRanks stores the very instance a
        // handler mutates, so an assertion against the stored aggregate
        // alone stays green even if the handler never calls save()/add()
        // — the decorator's call counts are what actually pin down that
        // persistence was requested.
        $this->countingRanks = new SaveCountingRanks($this->ranks);
        $this->roles = new InMemoryRoles();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
        $this->actors = new ActorAssembler();
    }

    #[Test]
    #[TestDox('DefineRankHandler defines a rank and dispatches RankDefined.')]
    public function define_rank_handler_defines_and_dispatches(): void
    {
        $handler = new DefineRankHandler(
            $this->countingRanks,
            new SequenceAdministrationIdentityGenerator(rankIds: [RankId::fromString(self::RANK_ID)]),
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $id = $handler(new DefineRank('Director', 20, ActorKind::System->value));

        self::assertSame(self::RANK_ID, $id->value);
        self::assertSame(1, $this->countingRanks->addCalls);
        $rank = $this->ranks->byId($id);
        self::assertSame('Director', $rank->name()->value);
        self::assertTrue($rank->seniority()->equals(SeniorityLevel::of(20)));
        self::assertCount(1, $this->eventBus->dispatchedMessages());
        self::assertInstanceOf(RankDefined::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('DefineRankHandler throws DuplicateRankName when a rank already has that name.')]
    public function define_rank_handler_rejects_duplicate_name(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);

        $handler = new DefineRankHandler(
            $this->countingRanks,
            new SequenceAdministrationIdentityGenerator(
                rankIds: [RankId::fromString('019571bf-5d51-7000-b500-00000000ba03')],
            ),
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(DuplicateRankName::class);
        $handler(new DefineRank('Director', 30, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('RenameRankHandler renames the rank, calls save(), and dispatches RankRenamed.')]
    public function rename_rank_handler_renames_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Old Name', 20);
        $handler = new RenameRankHandler($this->countingRanks, $this->clock, $this->actors, $this->eventBus);

        $handler(new RenameRank(self::RANK_ID, 'New Name', ActorKind::System->value));

        self::assertSame(1, $this->countingRanks->saveCalls);
        self::assertSame('New Name', $this->ranks->byId(RankId::fromString(self::RANK_ID))->name()->value);
        self::assertInstanceOf(RankRenamed::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('ChangeRankSeniorityHandler updates the level, calls save(), and dispatches RankSeniorityChanged.')]
    public function change_rank_seniority_handler_updates_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $handler = new ChangeRankSeniorityHandler($this->countingRanks, $this->clock, $this->actors, $this->eventBus);

        $handler(new ChangeRankSeniority(self::RANK_ID, 40, ActorKind::System->value));

        self::assertSame(1, $this->countingRanks->saveCalls);
        self::assertTrue(
            $this->ranks->byId(RankId::fromString(self::RANK_ID))->seniority()->equals(SeniorityLevel::of(40)),
        );
        self::assertInstanceOf(RankSeniorityChanged::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('GrantRoleToRankHandler grants the role, calls save(), and dispatches RoleGrantedToRank.')]
    public function grant_role_to_rank_handler_grants_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedRole(self::ROLE_ID, 'Front Desk');
        $handler = new GrantRoleToRankHandler(
            $this->countingRanks,
            $this->roles,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $handler(new GrantRoleToRank(self::RANK_ID, self::ROLE_ID, ActorKind::System->value));

        self::assertSame(1, $this->countingRanks->saveCalls);
        self::assertTrue(
            $this->ranks->byId(RankId::fromString(self::RANK_ID))->roles()->contains(RoleId::fromString(self::ROLE_ID)),
        );
        self::assertInstanceOf(RoleGrantedToRank::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('GrantRoleToRankHandler throws RoleNotFound when the role does not exist, without granting it.')]
    public function grant_role_to_rank_handler_rejects_unknown_role(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $handler = new GrantRoleToRankHandler(
            $this->countingRanks,
            $this->roles,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(RoleNotFound::class);
        $handler(new GrantRoleToRank(self::RANK_ID, self::ROLE_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('GrantRoleToRankHandler throws RoleIsRetired when the role has been retired, without granting it.')]
    public function grant_role_to_rank_handler_rejects_retired_role(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $this->seedRole(self::ROLE_ID, 'Front Desk', retired: true);
        $handler = new GrantRoleToRankHandler(
            $this->countingRanks,
            $this->roles,
            $this->clock,
            $this->actors,
            $this->eventBus,
        );

        $this->expectException(RoleIsRetired::class);
        $handler(new GrantRoleToRank(self::RANK_ID, self::ROLE_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('RevokeRoleFromRankHandler revokes the role, calls save(), and dispatches RoleRevokedFromRank.')]
    public function revoke_role_from_rank_handler_revokes_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20, AssignedRoles::of(RoleId::fromString(self::ROLE_ID)));
        $handler = new RevokeRoleFromRankHandler($this->countingRanks, $this->clock, $this->actors, $this->eventBus);

        $handler(new RevokeRoleFromRank(self::RANK_ID, self::ROLE_ID, ActorKind::System->value));

        self::assertSame(1, $this->countingRanks->saveCalls);
        self::assertFalse(
            $this->ranks->byId(RankId::fromString(self::RANK_ID))->roles()->contains(RoleId::fromString(self::ROLE_ID)),
        );
        self::assertInstanceOf(RoleRevokedFromRank::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('RetireRankHandler retires the rank, calls save(), and dispatches RankRetired.')]
    public function retire_rank_handler_retires_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $handler = new RetireRankHandler($this->countingRanks, $this->clock, $this->actors, $this->eventBus);

        $handler(new RetireRank(self::RANK_ID, ActorKind::System->value));

        self::assertSame(1, $this->countingRanks->saveCalls);
        self::assertTrue($this->ranks->byId(RankId::fromString(self::RANK_ID))->isRetired());
        self::assertInstanceOf(RankRetired::class, $this->eventBus->dispatchedMessages()[0]);
    }

    #[Test]
    #[TestDox('RetireRankHandler throws RankIsRetired when the rank is already retired.')]
    public function retire_rank_handler_rejects_double_retire(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $handler = new RetireRankHandler($this->countingRanks, $this->clock, $this->actors, $this->eventBus);
        $handler(new RetireRank(self::RANK_ID, ActorKind::System->value));

        $this->expectException(RankIsRetired::class);
        $handler(new RetireRank(self::RANK_ID, ActorKind::System->value));
    }

    #[Test]
    #[TestDox('ReinstateRankHandler clears retired, calls save(), and dispatches RankReinstated.')]
    public function reinstate_rank_handler_reinstates_and_dispatches(): void
    {
        $this->seedRank(self::RANK_ID, 'Director', 20);
        $retireHandler = new RetireRankHandler($this->countingRanks, $this->clock, $this->actors, $this->eventBus);
        $retireHandler(new RetireRank(self::RANK_ID, ActorKind::System->value));

        $handler = new ReinstateRankHandler($this->countingRanks, $this->clock, $this->actors, $this->eventBus);
        $handler(new ReinstateRank(self::RANK_ID, ActorKind::System->value));

        self::assertSame(2, $this->countingRanks->saveCalls);
        self::assertFalse($this->ranks->byId(RankId::fromString(self::RANK_ID))->isRetired());
        self::assertInstanceOf(RankReinstated::class, $this->eventBus->dispatchedMessages()[1]);
    }

    private function seedRank(string $id, string $name, int $seniority, ?AssignedRoles $roles = null): void
    {
        $rank = Rank::define(
            RankId::fromString($id),
            RankName::of($name),
            SeniorityLevel::of($seniority),
            $roles ?? AssignedRoles::none(),
            Actor::system(),
            $this->clock,
        );
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
