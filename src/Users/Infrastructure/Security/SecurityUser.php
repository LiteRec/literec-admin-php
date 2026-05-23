<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use App\Users\Domain\User;
use App\Users\Domain\ValueObject\Role;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Symfony-Security-facing projection of the User aggregate.
 *
 * Carries only the primitives Symfony's Security component needs; never
 * exposed to the Domain layer. EquatableInterface ensures Symfony
 * invalidates the session when the user is deactivated or the password
 * hash changes, without requiring an explicit logout flow.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    /**
     * @param list<string> $roles Role enum values plus 'ROLE_USER'.
     */
    public function __construct(
        public string $id,
        public string $username,
        public string $hashedPassword,
        public array $roles,
        public bool $isActive,
    ) {
    }

    public static function from(User $user): self
    {
        $roleValues = array_map(static fn(Role $r): string => $r->value, $user->roles());
        $roleValues[] = Role::User->value;

        return new self(
            id: $user->id()->value,
            username: $user->username()->value,
            hashedPassword: $user->passwordHash()->value,
            roles: array_values(array_unique($roleValues)),
            isActive: $user->isActive(),
        );
    }

    /**
     * The username field is constructed exclusively from Username::of(),
     * which rejects empty/whitespace input, so this projection is always
     * non-empty.
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        assert($this->username !== '');

        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->hashedPassword;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
        // No-op: the projection stores only the hashed password.
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->id === $this->id
            && $user->username === $this->username
            && $user->hashedPassword === $this->hashedPassword
            && $user->roles === $this->roles
            && $user->isActive === $this->isActive;
    }
}
