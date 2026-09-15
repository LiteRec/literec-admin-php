<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use App\Users\Domain\ValueObject\PasswordState;
use DateInterval;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Rejects authentication attempts from accounts that have been deactivated
 * via the User aggregate's deactivate() method, and from accounts whose
 * one-time password has already been consumed or has outlived its TTL
 * (LRA-213). Operates on SecurityUser because the firewall hydrates that
 * adapter, not the domain aggregate.
 *
 * The one-time-password checks run in checkPostAuth(), which Symfony's
 * UserCheckerListener only invokes after the submitted password has
 * matched — checking them in checkPreAuth() (as the "account disabled"
 * check above deliberately still does, matching Symfony's own reference
 * UserChecker) would let anyone learn an account's one-time-password state
 * by submitting its username with an arbitrary password, no credential
 * required.
 */
final class UserChecker implements UserCheckerInterface
{
    private const string ONE_TIME_PASSWORD_TTL = 'PT24H';

    private const string CONSUMED_MESSAGE
        = 'This one-time password has already been used. Ask a staff member to issue a new one.';

    private const string EXPIRED_MESSAGE
        = 'This one-time password has expired. Ask a staff member to issue a new one.';

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof SecurityUser) {
            return;
        }

        if (!$user->isActive) {
            throw new CustomUserMessageAccountStatusException('Your account is disabled.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof SecurityUser) {
            return;
        }

        if ($user->passwordState === PasswordState::OneTimeConsumed) {
            throw new CustomUserMessageAccountStatusException(self::CONSUMED_MESSAGE);
        }

        if ($this->oneTimePasswordHasExpired($user)) {
            throw new CustomUserMessageAccountStatusException(self::EXPIRED_MESSAGE);
        }
    }

    private function oneTimePasswordHasExpired(SecurityUser $user): bool
    {
        if ($user->passwordState !== PasswordState::OneTimeIssued || $user->oneTimePasswordIssuedAt === null) {
            return false;
        }

        $expiresAt = $user->oneTimePasswordIssuedAt->add(new DateInterval(self::ONE_TIME_PASSWORD_TTL));

        return $expiresAt < $this->clock->now();
    }
}
