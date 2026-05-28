<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Rejects authentication attempts from accounts that have been deactivated
 * via the User aggregate's deactivate() method. Operates on SecurityUser
 * because the firewall hydrates that adapter, not the domain aggregate.
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
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // No post-authentication checks required.
    }
}
