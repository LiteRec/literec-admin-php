<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use App\Users\Infrastructure\Persistence\Doctrine\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Rejects authentication attempts from accounts that have been disabled via
 * the User entity's isActive flag.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Your account is disabled.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-authentication checks required.
    }
}
