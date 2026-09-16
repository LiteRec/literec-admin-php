<?php

declare(strict_types=1);

namespace App\Users\Domain;

use App\Users\Domain\Event\OneTimePasswordConsumed;
use App\Users\Domain\Event\OneTimePasswordIssued;
use App\Users\Domain\Event\PasswordChanged;
use App\Users\Domain\Event\RoleGranted;
use App\Users\Domain\Event\RoleRevoked;
use App\Users\Domain\Event\UserDeactivated;
use App\Users\Domain\Event\UserReactivated;
use App\Users\Domain\Event\UserRegistered;
use App\Users\Domain\Exception\NoOneTimePasswordToConsume;
use App\Users\Domain\Exception\OneTimePasswordNotAllowed;
use App\Users\Domain\Exception\PasswordNotSet;
use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\PasswordCredential;
use App\Users\Domain\ValueObject\PasswordState;
use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\Roles;
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
    private PasswordState $passwordState;
    private ?DateTimeImmutable $oneTimePasswordIssuedAt = null;
    private Roles $roles;
    private bool $isActive;
    private DateTimeImmutable $createdAt;

    /**
     * Doctrine optimistic-lock version (LRA-213). Maintained by the ORM;
     * never mutated by domain code. Concurrent saves — most notably two
     * logins racing to consume the same one-time password — surface as
     * {@see \Doctrine\ORM\OptimisticLockException}, which
     * {@see \App\Users\Infrastructure\Persistence\Doctrine\DoctrineUsers::save()}
     * translates into {@see \App\Users\Domain\Exception\ConcurrentUserModification}.
     *
     * Exposed via {@see version()} so tests can pin the increment across
     * save operations and so PHPStan sees the property used.
     */
    private int $version = 0;

    private function __construct()
    {
        // Intentionally empty: a User is only ever built through the named
        // factory methods, which populate every property. The private
        // constructor exists solely to forbid direct instantiation.
    }

    public static function register(
        UserId $id,
        Username $username,
        HashedPassword $password,
        Roles $roles,
        ClockInterface $clock,
    ): self {
        $user = new self();
        $user->id = $id;
        $user->username = $username;
        $user->password = $password;
        $user->passwordState = PasswordState::Established;
        $user->roles = $roles;
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

    /**
     * Projects the three mapped password scalars into one value object.
     * See {@see PasswordCredential} for why they stay mapped individually.
     */
    public function credential(): PasswordCredential
    {
        return PasswordCredential::of($this->password, $this->passwordState, $this->oneTimePasswordIssuedAt);
    }

    public function roles(): Roles
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

    public function version(): int
    {
        return $this->version;
    }

    /**
     * Symfony's hash-upgrade path (rehashing on login when the algorithm's
     * cost parameters have changed). Deliberately leaves passwordState
     * untouched: an upgrade is not a credential issuance or consumption
     * event, so it must not affect the one-time-password lifecycle.
     */
    public function changePassword(HashedPassword $password, ClockInterface $clock): void
    {
        if ($this->password->equals($password)) {
            return;
        }

        $this->password = $password;
        $this->recordThat(new PasswordChanged($this->id, $clock->now()));
    }

    /**
     * Issues a temporary credential a staff member reads out once. The next
     * successful sign-in with it is forced to set a new password; a second
     * attempt to sign in with the same credential is rejected outright
     * (enforced by {@see \App\Users\Infrastructure\Security\UserChecker}
     * once consumeOneTimePassword() moves the state to OneTimeConsumed).
     *
     * @throws OneTimePasswordNotAllowed when the account is deactivated.
     */
    public function issueOneTimePassword(HashedPassword $hash, ClockInterface $clock): void
    {
        if (!$this->isActive) {
            throw OneTimePasswordNotAllowed::forInactiveUser($this->id->value);
        }

        $this->password = $hash;
        $this->passwordState = PasswordState::OneTimeIssued;
        $this->oneTimePasswordIssuedAt = $clock->now();
        $this->recordThat(new OneTimePasswordIssued($this->id, $clock->now()));
    }

    /**
     * Marks the currently issued one-time password as used. Called once,
     * on the first successful authentication with it; the account is then
     * forced through the "establish a new password" flow before any other
     * request is allowed to proceed.
     *
     * @throws NoOneTimePasswordToConsume when no one-time password is
     *         currently issued (state is not OneTimeIssued).
     */
    public function consumeOneTimePassword(ClockInterface $clock): void
    {
        if ($this->passwordState !== PasswordState::OneTimeIssued) {
            throw NoOneTimePasswordToConsume::for($this->id->value);
        }

        $this->passwordState = PasswordState::OneTimeConsumed;
        $this->recordThat(new OneTimePasswordConsumed($this->id, $clock->now()));
    }

    /**
     * The user-initiated password change that closes out a one-time
     * credential (or an ordinary voluntary password change once self-service
     * exists): returns the account to the Established state.
     */
    public function establishPassword(HashedPassword $password, ClockInterface $clock): void
    {
        $this->password = $password;
        $this->passwordState = PasswordState::Established;
        $this->oneTimePasswordIssuedAt = null;
        $this->recordThat(new PasswordChanged($this->id, $clock->now()));
    }

    public function grantRole(Role $role, ClockInterface $clock): void
    {
        if ($this->roles->contains($role)) {
            return;
        }

        $this->roles = $this->roles->with($role);
        $this->recordThat(new RoleGranted($this->id, $role, $clock->now()));
    }

    public function revokeRole(Role $role, ClockInterface $clock): void
    {
        if (!$this->roles->contains($role)) {
            return;
        }

        $this->roles = $this->roles->without($role);
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
}
