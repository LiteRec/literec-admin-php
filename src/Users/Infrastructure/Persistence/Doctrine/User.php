<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Persistence\Doctrine;

use App\Users\Domain\Exception\InvalidUsername;
use App\Users\Domain\Exception\PasswordNotSet;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Doctrine mapping lives in src/Users/Infrastructure/Persistence/Doctrine/Mapping/User.orm.xml
 * (LRA-18). LRA-19 will lift this class into App\Users\Domain\User and
 * swap the int id for a UUID v7 value object.
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private ?int $id = null;

    private string $username;

    /**
     * @var list<string> The user roles
     */
    private array $roles = [];

    /**
     * The hashed password.
     */
    private string $password = '';

    /**
     * Whether the account may sign in. This is application-level state:
     * Symfony's security layer does not check it automatically — the
     * UserChecker that enforces it is added with the authentication
     * backend (LRA-7).
     */
    private bool $isActive = true;

    private \DateTimeImmutable $createdAt;

    public function __construct(string $username)
    {
        if ($username === '') {
            throw InvalidUsername::empty();
        }

        $this->username = $username;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * A visual identifier that represents this user.
     *
     * The guard is unreachable for a constructed or hydrated user — the
     * constructor rejects empty usernames and the column is NOT NULL — but
     * it satisfies the non-empty-string contract of this interface method.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        if ($this->username === '') {
            throw InvalidUsername::empty();
        }

        return $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Guards against persisting a user that never had a password hash set.
     * Wired as prePersist + preUpdate lifecycle-callback in User.orm.xml.
     */
    public function assertPasswordIsSet(): void
    {
        if ($this->password === '') {
            throw PasswordNotSet::throw();
        }
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }
}
