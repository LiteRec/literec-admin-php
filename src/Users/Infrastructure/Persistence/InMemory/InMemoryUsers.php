<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Persistence\InMemory;

use App\Users\Domain\Exception\UserNotFound;
use App\Users\Domain\Exception\UsernameAlreadyTaken;
use App\Users\Domain\User;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;

/**
 * In-memory adapter for the {@see Users} port. Used by domain/application
 * unit tests so they stay #[Small] and never boot Doctrine.
 */
final class InMemoryUsers implements Users
{
    /** @var array<string, User> indexed by UserId->value */
    private array $byId = [];

    public function add(User $user): void
    {
        if ($this->existsWithUsername($user->username())) {
            throw UsernameAlreadyTaken::for($user->username()->value);
        }

        $this->byId[$user->id()->value] = $user;
    }

    public function save(User $user): void
    {
        $this->byId[$user->id()->value] = $user;
    }

    public function byId(UserId $id): User
    {
        return $this->byId[$id->value] ?? throw UserNotFound::byId($id->value);
    }

    public function byUsername(Username $username): User
    {
        foreach ($this->byId as $user) {
            if ($user->username()->equals($username)) {
                return $user;
            }
        }

        throw UserNotFound::byUsername($username->value);
    }

    public function existsWithUsername(Username $username): bool
    {
        foreach ($this->byId as $user) {
            if ($user->username()->equals($username)) {
                return true;
            }
        }

        return false;
    }
}
