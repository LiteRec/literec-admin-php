<?php

declare(strict_types=1);

namespace App\Users\Domain;

use App\Users\Domain\Event\PasswordChanged;
use App\Users\Domain\Event\RoleGranted;
use App\Users\Domain\Event\RoleRevoked;
use App\Users\Domain\Event\UserDeactivated;
use App\Users\Domain\Event\UserReactivated;
use App\Users\Domain\Event\UserRegistered;
use App\Users\Domain\Exception\PasswordNotSet;
use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Staff user aggregate.
 *
 * Pure domain class: no Symfony or Doctrine imports. The Infrastructure
 * layer adapts this aggregate to Symfony Security (via SecurityUser) and
 * Doctrine ORM (via XML mapping + custom DBAL types). All state changes
 * happen through intention-revealing methods that record domain events;
 * a Messenger middleware dispatches those events post-transaction.
 */
final class User
{
    use AggregateRoot;

    private UserId $id;
    private Username $username;
    private HashedPassword $password;
    /** @var list<Role> */
    private array $roles;
    private bool $isActive;
    private DateTimeImmutable $createdAt;

    private function __construct()
    {
        // Intentionally empty: a User is only ever built through the named
        // factory methods, which populate every property. The private
        // constructor exists solely to forbid direct instantiation.
    }

    /**
     * @param list<Role> $roles
     */
    public static function register(
        UserId $id,
        Username $username,
        HashedPassword $password,
        array $roles,
        ClockInterface $clock,
    ): self {
        $user = new self();
        $user->id = $id;
        $user->username = $username;
        $user->password = $password;
        $user->roles = self::deduplicate($roles);
        $user->isActive = true;
        $user->createdAt = $clock->now();
        $user->recordThat(new UserRegistered($id, $username, $user->createdAt));

        return $user;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function username(): Username
    {
        return $this->username;
    }

    public function passwordHash(): HashedPassword
    {
        return $this->password;
    }

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function changePassword(HashedPassword $password, ClockInterface $clock): void
    {
        if ($this->password->equals($password)) {
            return;
        }

        $this->password = $password;
        $this->recordThat(new PasswordChanged($this->id, $clock->now()));
    }

    public function grantRole(Role $role, ClockInterface $clock): void
    {
        if (in_array($role, $this->roles, true)) {
            return;
        }

        $this->roles[] = $role;
        $this->recordThat(new RoleGranted($this->id, $role, $clock->now()));
    }

    public function revokeRole(Role $role, ClockInterface $clock): void
    {
        $next = array_values(array_filter(
            $this->roles,
            static fn(Role $r): bool => $r !== $role,
        ));

        if (count($next) === count($this->roles)) {
            return;
        }

        $this->roles = $next;
        $this->recordThat(new RoleRevoked($this->id, $role, $clock->now()));
    }

    public function deactivate(string $reason, ClockInterface $clock): void
    {
        if (!$this->isActive) {
            return;
        }

        $this->isActive = false;
        $this->recordThat(new UserDeactivated($this->id, $reason, $clock->now()));
    }

    public function reactivate(ClockInterface $clock): void
    {
        if ($this->isActive) {
            return;
        }

        $this->isActive = true;
        $this->recordThat(new UserReactivated($this->id, $clock->now()));
    }

    /**
     * Wired as a Doctrine prePersist + preUpdate lifecycle callback via
     * the XML mapping (LRA-18). Guards a class invariant: the password
     * hash is always present.
     */
    public function assertPasswordIsSet(): void
    {
        if ($this->password->value === '') {
            throw PasswordNotSet::throw();
        }
    }

    /**
     * @param list<Role> $roles
     *
     * @return list<Role>
     */
    private static function deduplicate(array $roles): array
    {
        $seen = [];
        $result = [];
        foreach ($roles as $role) {
            if (!isset($seen[$role->value])) {
                $seen[$role->value] = true;
                $result[] = $role;
            }
        }

        return $result;
    }
}
