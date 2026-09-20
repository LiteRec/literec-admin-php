<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain;

use App\Administration\Domain\Event\RoleDefined;
use App\Administration\Domain\Event\RolePrivilegeGranted;
use App\Administration\Domain\Event\RolePrivilegeRevoked;
use App\Administration\Domain\Event\RolePrivilegesReplaced;
use App\Administration\Domain\Event\RoleRenamed;
use App\Administration\Domain\Event\RoleReworded;
use App\Administration\Domain\Event\RoleRetired;
use App\Administration\Domain\Exception\RoleAlreadyRetired;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\Role;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class RoleTest extends TestCase
{
    private const string ROLE_ID = '019571bf-5d51-7000-b500-00000000aa01';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    #[Test]
    #[TestDox('define() records RoleDefined with the acting actor.')]
    public function define_records_role_defined_with_actor(): void
    {
        $actor = Actor::system();
        $role = $this->defineRole(actor: $actor, privileges: PrivilegeSet::of(Privilege::ViewUsers));

        $events = $role->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleDefined::class, $events[0]);
        self::assertTrue($events[0]->actor->equals($actor));
        self::assertTrue($events[0]->privileges->equals(PrivilegeSet::of(Privilege::ViewUsers)));
        self::assertFalse($role->isRetired());
    }

    #[Test]
    #[TestDox('rename() updates the name and records RoleRenamed with the acting actor.')]
    public function rename_updates_name_and_records_event(): void
    {
        $role = $this->defineRole();
        $role->releaseEvents();
        $actor = Actor::system();

        $role->rename(RoleName::of('New Name'), $actor, $this->clock);

        self::assertSame('New Name', $role->name()->value);
        $events = $role->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleRenamed::class, $events[0]);
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('rename() is a no-op (records no event) when the name is unchanged.')]
    public function rename_is_a_no_op_when_unchanged(): void
    {
        $role = $this->defineRole(name: RoleName::of('Front Desk'));
        $role->releaseEvents();

        $role->rename(RoleName::of('front desk'), Actor::system(), $this->clock);

        self::assertSame([], $role->releaseEvents());
    }

    #[Test]
    #[TestDox('reword() updates the description and records RoleReworded with the acting actor.')]
    public function reword_updates_description_and_records_event(): void
    {
        $role = $this->defineRole();
        $role->releaseEvents();
        $actor = Actor::system();

        $role->reword(RoleDescription::of('New description.'), $actor, $this->clock);

        self::assertSame('New description.', $role->description()->value);
        $events = $role->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleReworded::class, $events[0]);
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('reword() is a no-op (records no event) when the description is unchanged.')]
    public function reword_is_a_no_op_when_unchanged(): void
    {
        $role = $this->defineRole(description: RoleDescription::of('Same.'));
        $role->releaseEvents();

        $role->reword(RoleDescription::of('Same.'), Actor::system(), $this->clock);

        self::assertSame([], $role->releaseEvents());
    }

    #[Test]
    #[TestDox('grant() adds the privilege and records RolePrivilegeGranted with the acting actor.')]
    public function grant_adds_privilege_and_records_event(): void
    {
        $role = $this->defineRole(privileges: PrivilegeSet::none());
        $role->releaseEvents();
        $actor = Actor::system();

        $role->grant(Privilege::ViewUsers, $actor, $this->clock);

        self::assertTrue($role->privileges()->contains(Privilege::ViewUsers));
        $events = $role->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RolePrivilegeGranted::class, $events[0]);
        self::assertSame(Privilege::ViewUsers, $events[0]->privilege);
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('grant() is a no-op (records no event) when the privilege is already granted.')]
    public function grant_is_a_no_op_when_already_granted(): void
    {
        $role = $this->defineRole(privileges: PrivilegeSet::of(Privilege::ViewUsers));
        $role->releaseEvents();

        $role->grant(Privilege::ViewUsers, Actor::system(), $this->clock);

        self::assertSame([], $role->releaseEvents());
    }

    #[Test]
    #[TestDox('revoke() removes the privilege and records RolePrivilegeRevoked with the acting actor.')]
    public function revoke_removes_privilege_and_records_event(): void
    {
        $role = $this->defineRole(privileges: PrivilegeSet::of(Privilege::ViewUsers));
        $role->releaseEvents();
        $actor = Actor::system();

        $role->revoke(Privilege::ViewUsers, $actor, $this->clock);

        self::assertFalse($role->privileges()->contains(Privilege::ViewUsers));
        $events = $role->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RolePrivilegeRevoked::class, $events[0]);
        self::assertSame(Privilege::ViewUsers, $events[0]->privilege);
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('revoke() is a no-op (records no event) when the privilege is not granted.')]
    public function revoke_is_a_no_op_when_not_granted(): void
    {
        $role = $this->defineRole(privileges: PrivilegeSet::none());
        $role->releaseEvents();

        $role->revoke(Privilege::ViewUsers, Actor::system(), $this->clock);

        self::assertSame([], $role->releaseEvents());
    }

    #[Test]
    #[TestDox('replacePrivileges() swaps the bundle and records RolePrivilegesReplaced with before/after + actor.')]
    public function replace_privileges_updates_bundle_and_records_event(): void
    {
        $before = PrivilegeSet::of(Privilege::ViewUsers);
        $after = PrivilegeSet::of(Privilege::AddUsers, Privilege::EditUsers);
        $role = $this->defineRole(privileges: $before);
        $role->releaseEvents();
        $actor = Actor::system();

        $role->replacePrivileges($after, $actor, $this->clock);

        self::assertTrue($role->privileges()->equals($after));
        $events = $role->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RolePrivilegesReplaced::class, $events[0]);
        self::assertTrue($events[0]->before->equals($before));
        self::assertTrue($events[0]->after->equals($after));
        self::assertTrue($events[0]->actor->equals($actor));
    }

    #[Test]
    #[TestDox('replacePrivileges() is a no-op (records no event) when the bundle is unchanged.')]
    public function replace_privileges_is_a_no_op_when_unchanged(): void
    {
        $privileges = PrivilegeSet::of(Privilege::ViewUsers, Privilege::AddUsers);
        $role = $this->defineRole(privileges: $privileges);
        $role->releaseEvents();

        // Same set, different construction order — equals() ignores order.
        $unchanged = PrivilegeSet::of(Privilege::AddUsers, Privilege::ViewUsers);
        $role->replacePrivileges($unchanged, Actor::system(), $this->clock);

        self::assertSame([], $role->releaseEvents());
    }

    #[Test]
    #[TestDox('retire() marks the role retired and records RoleRetired with the acting actor.')]
    public function retire_marks_retired_and_records_event(): void
    {
        $role = $this->defineRole();
        $role->releaseEvents();
        $actor = Actor::system();

        $role->retire($actor, $this->clock);

        self::assertTrue($role->isRetired());
        $events = $role->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleRetired::class, $events[0]);
        self::assertTrue($events[0]->actor->equals($actor));
    }

    /**
     * @return Generator<string, array{0: callable(Role, Actor, MockClock): void}>
     */
    public static function everyMutator(): Generator
    {
        yield 'rename' => [self::rename(...)];
        yield 'reword' => [self::reword(...)];
        yield 'grant' => [self::grant(...)];
        yield 'revoke' => [self::revoke(...)];
        yield 'replacePrivileges' => [self::replacePrivileges(...)];
        yield 'retire' => [self::retire(...)];
    }

    private static function rename(Role $role, Actor $actor, MockClock $clock): void
    {
        $role->rename(RoleName::of('Anything'), $actor, $clock);
    }

    private static function reword(Role $role, Actor $actor, MockClock $clock): void
    {
        $role->reword(RoleDescription::of('Anything.'), $actor, $clock);
    }

    private static function grant(Role $role, Actor $actor, MockClock $clock): void
    {
        $role->grant(Privilege::ViewUsers, $actor, $clock);
    }

    private static function revoke(Role $role, Actor $actor, MockClock $clock): void
    {
        $role->revoke(Privilege::ViewUsers, $actor, $clock);
    }

    private static function replacePrivileges(Role $role, Actor $actor, MockClock $clock): void
    {
        $role->replacePrivileges(PrivilegeSet::of(Privilege::AddUsers), $actor, $clock);
    }

    private static function retire(Role $role, Actor $actor, MockClock $clock): void
    {
        $role->retire($actor, $clock);
    }

    /**
     * @param callable(Role, Actor, MockClock): void $mutate
     */
    #[Test]
    #[DataProvider('everyMutator')]
    #[TestDox('Every mutator throws RoleAlreadyRetired once the role is retired: $_dataName.')]
    public function every_mutator_throws_once_retired(callable $mutate): void
    {
        $role = $this->defineRole();
        $role->retire(Actor::system(), $this->clock);
        $role->releaseEvents();

        $this->expectException(RoleAlreadyRetired::class);

        $mutate($role, Actor::system(), $this->clock);
    }

    private function defineRole(
        ?RoleName $name = null,
        ?RoleDescription $description = null,
        ?PrivilegeSet $privileges = null,
        ?Actor $actor = null,
    ): Role {
        return Role::define(
            RoleId::fromString(self::ROLE_ID),
            $name ?? RoleName::of('Front Desk'),
            $description ?? RoleDescription::of('Front-of-house operations.'),
            $privileges ?? PrivilegeSet::of(Privilege::ViewUsers),
            $actor ?? Actor::system(),
            $this->clock,
        );
    }
}
