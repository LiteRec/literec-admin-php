<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain;

use App\Administration\Domain\Event\RankDefined;
use App\Administration\Domain\Event\RankReinstated;
use App\Administration\Domain\Event\RankRenamed;
use App\Administration\Domain\Event\RankRetired;
use App\Administration\Domain\Event\RankSeniorityChanged;
use App\Administration\Domain\Event\RoleGrantedToRank;
use App\Administration\Domain\Event\RoleRevokedFromRank;
use App\Administration\Domain\Exception\RankIsRetired;
use App\Administration\Domain\Rank;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class RankTest extends TestCase
{
    private const string RANK_ID = '019571bf-5d51-7000-b500-00000000ea01';
    private const string ROLE_ID = '019571bf-5d51-7000-b500-00000000ea02';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    #[Test]
    #[TestDox('define() records RankDefined with the acting actor.')]
    public function define_records_rank_defined_with_actor(): void
    {
        $actor = Actor::system();
        $rank = $this->defineRank(actor: $actor, seniority: SeniorityLevel::of(30));

        $events = $rank->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RankDefined::class, $events[0]);
        self::assertTrue($events[0]->actor->equals($actor));
        self::assertTrue($events[0]->seniority->equals(SeniorityLevel::of(30)));
        self::assertFalse($rank->isRetired());
    }

    #[Test]
    #[TestDox('rename() updates the name and records RankRenamed with the acting actor.')]
    public function rename_updates_name_and_records_event(): void
    {
        $rank = $this->defineRank();
        $rank->releaseEvents();
        $actor = Actor::system();

        $rank->rename(RankName::of('New Name'), $actor, $this->clock);

        self::assertSame('New Name', $rank->name()->value);
        $events = $rank->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RankRenamed::class, $events[0]);
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('rename() is a no-op (records no event) when the name is unchanged.')]
    public function rename_is_a_no_op_when_unchanged(): void
    {
        $rank = $this->defineRank(name: RankName::of('Director'));
        $rank->releaseEvents();

        $rank->rename(RankName::of('Director'), Actor::system(), $this->clock);

        self::assertSame([], $rank->releaseEvents());
    }

    #[Test]
    #[TestDox('rename() records an event for a case-only change, unlike the case-insensitive equals() guard.')]
    public function rename_records_event_for_a_case_only_change(): void
    {
        $rank = $this->defineRank(name: RankName::of('director'));
        $rank->releaseEvents();

        $rank->rename(RankName::of('Director'), Actor::system(), $this->clock);

        self::assertSame('Director', $rank->name()->value);
        $events = $rank->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RankRenamed::class, $events[0]);
    }

    #[Test]
    #[TestDox('changeSeniority() updates the level and records RankSeniorityChanged with the acting actor.')]
    public function change_seniority_updates_level_and_records_event(): void
    {
        $rank = $this->defineRank(seniority: SeniorityLevel::of(30));
        $rank->releaseEvents();
        $actor = Actor::system();

        $rank->changeSeniority(SeniorityLevel::of(40), $actor, $this->clock);

        self::assertTrue($rank->seniority()->equals(SeniorityLevel::of(40)));
        $events = $rank->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RankSeniorityChanged::class, $events[0]);
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('changeSeniority() is a no-op (records no event) when the level is unchanged.')]
    public function change_seniority_is_a_no_op_when_unchanged(): void
    {
        $rank = $this->defineRank(seniority: SeniorityLevel::of(30));
        $rank->releaseEvents();

        $rank->changeSeniority(SeniorityLevel::of(30), Actor::system(), $this->clock);

        self::assertSame([], $rank->releaseEvents());
    }

    #[Test]
    #[TestDox('grantRole() adds the role and records RoleGrantedToRank with the acting actor.')]
    public function grant_role_adds_role_and_records_event(): void
    {
        $rank = $this->defineRank();
        $rank->releaseEvents();
        $actor = Actor::system();
        $roleId = RoleId::fromString(self::ROLE_ID);

        $rank->grantRole($roleId, $actor, $this->clock);

        self::assertTrue($rank->roles()->contains($roleId));
        $events = $rank->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleGrantedToRank::class, $events[0]);
        self::assertTrue($events[0]->roleId->equals($roleId));
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('grantRole() is a no-op (records no event) when the role is already granted.')]
    public function grant_role_is_a_no_op_when_already_granted(): void
    {
        $roleId = RoleId::fromString(self::ROLE_ID);
        $rank = $this->defineRank(roles: AssignedRoles::of($roleId));
        $rank->releaseEvents();

        $rank->grantRole($roleId, Actor::system(), $this->clock);

        self::assertSame([], $rank->releaseEvents());
    }

    #[Test]
    #[TestDox('revokeRole() removes the role and records RoleRevokedFromRank with the acting actor.')]
    public function revoke_role_removes_role_and_records_event(): void
    {
        $roleId = RoleId::fromString(self::ROLE_ID);
        $rank = $this->defineRank(roles: AssignedRoles::of($roleId));
        $rank->releaseEvents();
        $actor = Actor::system();

        $rank->revokeRole($roleId, $actor, $this->clock);

        self::assertFalse($rank->roles()->contains($roleId));
        $events = $rank->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleRevokedFromRank::class, $events[0]);
        self::assertTrue($events[0]->roleId->equals($roleId));
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('revokeRole() is a no-op (records no event) when the role is not granted.')]
    public function revoke_role_is_a_no_op_when_not_granted(): void
    {
        $rank = $this->defineRank(roles: AssignedRoles::none());
        $rank->releaseEvents();

        $rank->revokeRole(RoleId::fromString(self::ROLE_ID), Actor::system(), $this->clock);

        self::assertSame([], $rank->releaseEvents());
    }

    #[Test]
    #[TestDox('retire() marks the rank retired and records RankRetired with the acting actor.')]
    public function retire_marks_retired_and_records_event(): void
    {
        $rank = $this->defineRank();
        $rank->releaseEvents();
        $actor = Actor::system();

        $rank->retire($actor, $this->clock);

        self::assertTrue($rank->isRetired());
        $events = $rank->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RankRetired::class, $events[0]);
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('reinstate() clears retired and records RankReinstated with the acting actor.')]
    public function reinstate_clears_retired_and_records_event(): void
    {
        $rank = $this->defineRank();
        $rank->retire(Actor::system(), $this->clock);
        $rank->releaseEvents();
        $actor = Actor::system();

        $rank->reinstate($actor, $this->clock);

        self::assertFalse($rank->isRetired());
        $events = $rank->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RankReinstated::class, $events[0]);
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('reinstate() is a no-op (records no event) when the rank is already active.')]
    public function reinstate_is_a_no_op_when_already_active(): void
    {
        $rank = $this->defineRank();
        $rank->releaseEvents();

        $rank->reinstate(Actor::system(), $this->clock);

        self::assertSame([], $rank->releaseEvents());
        self::assertFalse($rank->isRetired());
    }

    /**
     * @return Generator<string, array{0: callable(Rank, Actor, MockClock): void}>
     */
    public static function everyMutatorExceptReinstate(): Generator
    {
        yield 'rename' => [self::rename(...)];
        yield 'changeSeniority' => [self::changeSeniority(...)];
        yield 'grantRole' => [self::grantRole(...)];
        yield 'revokeRole' => [self::revokeRole(...)];
        yield 'retire' => [self::retire(...)];
    }

    private static function rename(Rank $rank, Actor $actor, MockClock $clock): void
    {
        $rank->rename(RankName::of('Anything'), $actor, $clock);
    }

    private static function changeSeniority(Rank $rank, Actor $actor, MockClock $clock): void
    {
        $rank->changeSeniority(SeniorityLevel::of(99), $actor, $clock);
    }

    private static function grantRole(Rank $rank, Actor $actor, MockClock $clock): void
    {
        $rank->grantRole(RoleId::fromString(self::ROLE_ID), $actor, $clock);
    }

    private static function revokeRole(Rank $rank, Actor $actor, MockClock $clock): void
    {
        $rank->revokeRole(RoleId::fromString(self::ROLE_ID), $actor, $clock);
    }

    private static function retire(Rank $rank, Actor $actor, MockClock $clock): void
    {
        $rank->retire($actor, $clock);
    }

    /**
     * @param callable(Rank, Actor, MockClock): void $mutate
     */
    #[Test]
    #[DataProvider('everyMutatorExceptReinstate')]
    #[TestDox('Every mutator but reinstate() throws RankIsRetired once the rank is retired: $_dataName.')]
    public function every_mutator_except_reinstate_throws_once_retired(callable $mutate): void
    {
        $rank = $this->defineRank();
        $rank->retire(Actor::system(), $this->clock);
        $rank->releaseEvents();

        $this->expectException(RankIsRetired::class);

        $mutate($rank, Actor::system(), $this->clock);
    }

    private function defineRank(
        ?RankName $name = null,
        ?SeniorityLevel $seniority = null,
        ?AssignedRoles $roles = null,
        ?Actor $actor = null,
    ): Rank {
        return Rank::define(
            RankId::fromString(self::RANK_ID),
            $name ?? RankName::of('Director'),
            $seniority ?? SeniorityLevel::of(20),
            $roles ?? AssignedRoles::none(),
            $actor ?? Actor::system(),
            $this->clock,
        );
    }
}
