<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain;

use App\Tests\Support\Fake\SequenceIdentityGenerator;
use App\Users\Domain\Event\PasswordChanged;
use App\Users\Domain\Event\RoleGranted;
use App\Users\Domain\Event\RoleRevoked;
use App\Users\Domain\Event\UserDeactivated;
use App\Users\Domain\Event\UserReactivated;
use App\Users\Domain\Event\UserRegistered;
use App\Users\Domain\User;
use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class UserTest extends TestCase
{
    private const string SAMPLE_HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR';

    private MockClock $clock;
    private SequenceIdentityGenerator $ids;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $this->ids = new SequenceIdentityGenerator(
            UserId::fromString('019571bf-5d51-7000-b500-000000000001'),
        );
    }

    #[Test]
    #[TestDox('::register() records UserRegistered with id, username, and the clock instant.')]
    public function register_records_user_registered(): void
    {
        $id = $this->ids->nextUserId();
        $user = User::register(
            $id,
            Username::of('alice'),
            HashedPassword::fromHash(self::SAMPLE_HASH),
            [Role::User],
            $this->clock,
        );

        $events = $user->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(UserRegistered::class, $events[0]);
        self::assertTrue($events[0]->userId->equals($id));
        self::assertSame('alice', $events[0]->username->value);
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);
    }

    #[Test]
    #[TestDox('::release_events() returns the buffer and clears it.')]
    public function release_events_clears_the_buffer(): void
    {
        $user = $this->register();

        self::assertCount(1, $user->releaseEvents());
        self::assertSame([], $user->releaseEvents());
    }

    #[Test]
    #[TestDox('::changePassword() records PasswordChanged and updates the hash.')]
    public function change_password_records_password_changed(): void
    {
        $user = $this->register();
        $user->releaseEvents();

        $newHash = '$2y$10$zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz0';
        $user->changePassword(HashedPassword::fromHash($newHash), $this->clock);

        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PasswordChanged::class, $events[0]);
        self::assertTrue($events[0]->userId->equals($user->id()));
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);
        self::assertSame($newHash, $user->passwordHash()->value);
    }

    #[Test]
    #[TestDox('::changePassword() is a no-op when the hash is identical.')]
    public function change_password_is_idempotent_when_hash_is_identical(): void
    {
        $user = $this->register();
        $user->releaseEvents();

        $user->changePassword(HashedPassword::fromHash(self::SAMPLE_HASH), $this->clock);

        self::assertSame([], $user->releaseEvents());
    }

    #[Test]
    #[TestDox('::grantRole() records RoleGranted and adds the role.')]
    public function grant_role_records_role_granted(): void
    {
        $user = $this->register();
        $user->releaseEvents();

        $user->grantRole(Role::Admin, $this->clock);

        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleGranted::class, $events[0]);
        self::assertTrue($events[0]->userId->equals($user->id()));
        self::assertSame(Role::Admin, $events[0]->role);
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);
        self::assertContains(Role::Admin, $user->roles());
    }

    #[Test]
    #[TestDox('::grantRole() is a no-op when the role is already granted.')]
    public function grant_role_is_idempotent(): void
    {
        $user = $this->register();
        $user->releaseEvents();

        $user->grantRole(Role::User, $this->clock);

        self::assertSame([], $user->releaseEvents());
    }

    #[Test]
    #[TestDox('::revokeRole() records RoleRevoked and removes the role.')]
    public function revoke_role_records_role_revoked(): void
    {
        $user = $this->register();
        $user->grantRole(Role::Admin, $this->clock);
        $user->releaseEvents();

        $user->revokeRole(Role::Admin, $this->clock);

        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleRevoked::class, $events[0]);
        self::assertTrue($events[0]->userId->equals($user->id()));
        self::assertSame(Role::Admin, $events[0]->role);
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);
        self::assertNotContains(Role::Admin, $user->roles());
    }

    #[Test]
    #[TestDox('::revokeRole() is a no-op when the role is not granted.')]
    public function revoke_role_is_idempotent(): void
    {
        $user = $this->register();
        $user->releaseEvents();

        $user->revokeRole(Role::Admin, $this->clock);

        self::assertSame([], $user->releaseEvents());
        self::assertNotContains(Role::Admin, $user->roles());
    }

    #[Test]
    #[TestDox('::deactivate() records UserDeactivated and flips isActive to false.')]
    public function deactivate_records_user_deactivated(): void
    {
        $user = $this->register();
        $user->releaseEvents();

        $user->deactivate('superseded', $this->clock);

        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserDeactivated::class, $events[0]);
        self::assertTrue($events[0]->userId->equals($user->id()));
        self::assertSame('superseded', $events[0]->reason);
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);
        self::assertFalse($user->isActive());
    }

    #[Test]
    #[TestDox('::reactivate() records UserReactivated and flips isActive back to true.')]
    public function reactivate_records_user_reactivated(): void
    {
        $user = $this->register();
        $user->deactivate('temporary', $this->clock);
        $user->releaseEvents();

        $user->reactivate($this->clock);

        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserReactivated::class, $events[0]);
        self::assertTrue($events[0]->userId->equals($user->id()));
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);
        self::assertTrue($user->isActive());
    }

    #[Test]
    #[TestDox('::deactivate() is a no-op when the user is already inactive.')]
    public function deactivate_is_idempotent(): void
    {
        $user = $this->register();
        $user->deactivate('first reason', $this->clock);
        $user->releaseEvents();

        $user->deactivate('second reason', $this->clock);

        self::assertSame([], $user->releaseEvents());
        self::assertFalse($user->isActive());
    }

    #[Test]
    #[TestDox('::reactivate() is a no-op when the user is already active.')]
    public function reactivate_is_idempotent(): void
    {
        $user = $this->register();
        $user->releaseEvents();

        $user->reactivate($this->clock);

        self::assertSame([], $user->releaseEvents());
        self::assertTrue($user->isActive());
    }

    #[Test]
    #[TestDox('::assertPasswordIsSet() is a no-op for a normally constructed user.')]
    public function assert_password_is_set_passes_when_hash_is_present(): void
    {
        // The PasswordNotSet branch can only fire if a Doctrine hydration
        // bypasses HashedPassword::fromHash() with an empty string, which
        // the value object itself rejects at construction time. Asserting
        // the negative case here would require forcing an invariant-violating
        // state via reflection — outside the public contract.
        $this->expectNotToPerformAssertions();

        $user = $this->register();

        $user->assertPasswordIsSet();
    }

    private function register(): User
    {
        return User::register(
            $this->ids->nextUserId(),
            Username::of('alice'),
            HashedPassword::fromHash(self::SAMPLE_HASH),
            [Role::User],
            $this->clock,
        );
    }
}
