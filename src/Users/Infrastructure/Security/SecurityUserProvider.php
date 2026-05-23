<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use App\Users\Application\Command\ChangeUserPassword;
use App\Users\Domain\Exception\UserNotFound;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Bridges Symfony Security to the Users bounded context. Loads SecurityUser
 * projections via the {@see Users} port and routes password upgrades through
 * a ChangeUserPassword command on the command bus.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final class SecurityUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly Users $users,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $user = $this->users->byUsername(Username::of($identifier));
        } catch (UserNotFound $e) {
            throw new UserNotFoundException(previous: $e);
        }

        return SecurityUser::from($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        try {
            $fresh = $this->users->byId(UserId::fromString($user->id));
        } catch (UserNotFound $e) {
            throw new UserNotFoundException(previous: $e);
        }

        return SecurityUser::from($fresh);
    }

    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class || is_subclass_of($class, SecurityUser::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof SecurityUser) {
            return;
        }

        $this->commandBus->dispatch(new ChangeUserPassword(
            userId: $user->id,
            newHashedPassword: $newHashedPassword,
        ));
    }
}
