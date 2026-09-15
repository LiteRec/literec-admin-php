<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Infrastructure\Security;

use App\Users\Domain\ValueObject\PasswordState;
use App\Users\Infrastructure\Security\SecurityUser;
use App\Users\Infrastructure\Security\UserChecker;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

#[Small]
final class UserCheckerTest extends TestCase
{
    private MockClock $clock;
    private UserChecker $checker;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $this->checker = new UserChecker($this->clock);
    }

    #[Test]
    #[TestDox('checkPreAuth() rejects a deactivated account.')]
    public function pre_auth_rejects_a_deactivated_account(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Your account is disabled.');

        $this->checker->checkPreAuth($this->securityUser(isActive: false, state: PasswordState::Established));
    }

    #[Test]
    #[DataProvider('passwordStates')]
    #[TestDox('checkPreAuth() ignores passwordState so it is never revealed before credentials are checked.')]
    public function pre_auth_ignores_password_state(PasswordState $state): void
    {
        $this->expectNotToPerformAssertions();

        $this->checker->checkPreAuth($this->securityUser(isActive: true, state: $state));
    }

    public static function passwordStates(): Generator
    {
        yield 'Established' => [PasswordState::Established];
        yield 'OneTimeIssued' => [PasswordState::OneTimeIssued];
        yield 'OneTimeConsumed' => [PasswordState::OneTimeConsumed];
    }

    #[Test]
    #[TestDox('checkPostAuth() rejects an account whose one-time password has already been consumed.')]
    public function post_auth_rejects_a_consumed_one_time_password(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage(
            'This one-time password has already been used. Ask a staff member to issue a new one.',
        );

        $this->checker->checkPostAuth($this->securityUser(isActive: true, state: PasswordState::OneTimeConsumed));
    }

    #[Test]
    #[TestDox('checkPostAuth() accepts an active account with a freshly issued one-time password.')]
    public function post_auth_accepts_a_freshly_issued_one_time_password(): void
    {
        $this->expectNotToPerformAssertions();

        $this->checker->checkPostAuth($this->securityUser(
            isActive: true,
            state: PasswordState::OneTimeIssued,
            oneTimePasswordIssuedAt: $this->clock->now(),
        ));
    }

    #[Test]
    #[TestDox('checkPostAuth() rejects a one-time password older than the TTL.')]
    public function post_auth_rejects_an_expired_one_time_password(): void
    {
        $issuedAt = $this->clock->now()->modify('-25 hours');

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage(
            'This one-time password has expired. Ask a staff member to issue a new one.',
        );

        $this->checker->checkPostAuth($this->securityUser(
            isActive: true,
            state: PasswordState::OneTimeIssued,
            oneTimePasswordIssuedAt: $issuedAt,
        ));
    }

    #[Test]
    #[TestDox('checkPostAuth() accepts an active account in the Established state.')]
    public function post_auth_accepts_an_active_established_account(): void
    {
        $this->expectNotToPerformAssertions();

        $this->checker->checkPostAuth($this->securityUser(isActive: true, state: PasswordState::Established));
    }

    private function securityUser(
        bool $isActive,
        PasswordState $state,
        ?DateTimeImmutable $oneTimePasswordIssuedAt = null,
    ): SecurityUser {
        return new SecurityUser(
            id: '019571bf-5d51-7000-b500-000000000001',
            username: 'alice',
            hashedPassword: '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR',
            roles: ['ROLE_USER'],
            isActive: $isActive,
            passwordState: $state,
            oneTimePasswordIssuedAt: $oneTimePasswordIssuedAt,
        );
    }
}
