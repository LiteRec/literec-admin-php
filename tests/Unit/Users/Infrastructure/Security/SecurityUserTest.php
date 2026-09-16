<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Infrastructure\Security;

use App\Users\Domain\User;
use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\PasswordState;
use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\Roles;
use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;
use App\Users\Infrastructure\Security\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class SecurityUserTest extends TestCase
{
    private const string SAMPLE_HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR';

    #[Test]
    #[TestDox('::from() projects the aggregate\'s passwordState.')]
    public function from_projects_the_aggregates_password_state(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $user = User::register(
            UserId::fromString('019571bf-5d51-7000-b500-000000000001'),
            Username::of('alice'),
            HashedPassword::fromHash(self::SAMPLE_HASH),
            Roles::of(Role::User),
            $clock,
        );
        $user->issueOneTimePassword(HashedPassword::fromHash(self::SAMPLE_HASH), $clock);

        $securityUser = SecurityUser::from($user);

        self::assertSame(PasswordState::OneTimeIssued, $securityUser->passwordState);
    }

    #[Test]
    #[TestDox('::from() projects the aggregate\'s one-time-password issuance instant.')]
    public function from_projects_the_one_time_password_issued_at(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $user = User::register(
            UserId::fromString('019571bf-5d51-7000-b500-000000000001'),
            Username::of('alice'),
            HashedPassword::fromHash(self::SAMPLE_HASH),
            Roles::of(Role::User),
            $clock,
        );
        $user->issueOneTimePassword(HashedPassword::fromHash(self::SAMPLE_HASH), $clock);

        $securityUser = SecurityUser::from($user);

        self::assertEquals($clock->now(), $securityUser->oneTimePasswordIssuedAt);
    }

    #[Test]
    #[TestDox('::isEqualTo() ignores passwordState so a consumed one-time password does not invalidate the session.')]
    public function is_equal_to_ignores_password_state(): void
    {
        $issued = $this->securityUser(PasswordState::OneTimeIssued);
        $consumed = $this->securityUser(PasswordState::OneTimeConsumed);

        self::assertTrue($issued->isEqualTo($consumed));
    }

    #[Test]
    #[TestDox('::isEqualTo() still detects a changed password hash.')]
    public function is_equal_to_detects_a_changed_hash(): void
    {
        $original = $this->securityUser(PasswordState::Established);
        $changed = new SecurityUser(
            id: $original->id,
            username: $original->username,
            hashedPassword: '$2y$10$zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz0',
            roles: $original->roles,
            isActive: $original->isActive,
            passwordState: $original->passwordState,
        );

        self::assertFalse($original->isEqualTo($changed));
    }

    private function securityUser(PasswordState $state): SecurityUser
    {
        return new SecurityUser(
            id: '019571bf-5d51-7000-b500-000000000001',
            username: 'alice',
            hashedPassword: self::SAMPLE_HASH,
            roles: ['ROLE_USER'],
            isActive: true,
            passwordState: $state,
        );
    }
}
