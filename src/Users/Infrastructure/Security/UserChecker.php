<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use App\Users\Domain\ValueObject\PasswordState;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Rejects authentication attempts from accounts that have been deactivated
 * via the User aggregate's deactivate() method, and from accounts whose
 * one-time password has already been consumed (LRA-213). Operates on
 * SecurityUser because the firewall hydrates that adapter, not the domain
 * aggregate.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof SecurityUser) {
            return;
        }

        if (!$user->isActive) {
            throw new CustomUserMessageAccountStatusException('Your account is disabled.');
        }

        if ($user->passwordState === PasswordState::OneTimeConsumed) {
            throw new CustomUserMessageAccountStatusException(
                'This one-time password has already been used. Ask a staff member to issue a new one.',
            );
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // No post-authentication checks required.
    }
}
