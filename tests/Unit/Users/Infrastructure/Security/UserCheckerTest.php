<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Infrastructure\Security;

use App\Users\Domain\ValueObject\PasswordState;
use App\Users\Infrastructure\Security\SecurityUser;
use App\Users\Infrastructure\Security\UserChecker;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

#[Small]
final class UserCheckerTest extends TestCase
{
    private UserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserChecker();
    }

    #[Test]
    #[TestDox('Rejects a deactivated account.')]
    public function rejects_a_deactivated_account(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Your account is disabled.');

        $this->checker->checkPreAuth($this->securityUser(isActive: false, state: PasswordState::Established));
    }

    #[Test]
    #[TestDox('Rejects an account whose one-time password has already been consumed.')]
    public function rejects_a_consumed_one_time_password(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage(
            'This one-time password has already been used. Ask a staff member to issue a new one.',
        );

        $this->checker->checkPreAuth($this->securityUser(isActive: true, state: PasswordState::OneTimeConsumed));
    }

    #[Test]
    #[TestDox('Accepts an active account with an issued (unconsumed) one-time password.')]
    public function accepts_an_active_account_with_an_issued_one_time_password(): void
    {
        $this->expectNotToPerformAssertions();

        $this->checker->checkPreAuth($this->securityUser(isActive: true, state: PasswordState::OneTimeIssued));
    }

    #[Test]
    #[TestDox('Accepts an active account in the Established state.')]
    public function accepts_an_active_established_account(): void
    {
        $this->expectNotToPerformAssertions();

        $this->checker->checkPreAuth($this->securityUser(isActive: true, state: PasswordState::Established));
    }

    private function securityUser(bool $isActive, PasswordState $state): SecurityUser
    {
        return new SecurityUser(
            id: '019571bf-5d51-7000-b500-000000000001',
            username: 'alice',
            hashedPassword: '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR',
            roles: ['ROLE_USER'],
            isActive: $isActive,
            passwordState: $state,
        );
    }
}
