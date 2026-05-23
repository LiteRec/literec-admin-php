<?php

declare(strict_types=1);

namespace App\Users\Domain;

use App\Users\Domain\ValueObject\UserId;
use App\Users\Domain\ValueObject\Username;

/**
 * Domain port for persisting and retrieving User aggregates.
 *
 * Forbids generic finders ({@see https://github.com/doctrine/orm} `findBy`,
 * `findOneBy`, `createQueryBuilder` etc.); every accessor is named after
 * a domain question staff/admin users actually ask.
 */
interface Users
{
    /**
     * Persists a newly registered user.
     *
     * @throws \App\Users\Domain\Exception\UsernameAlreadyTaken when a user
     *         with the same username already exists (caught via the unique
     *         constraint on race conditions).
     */
    public function add(User $user): void;

    /**
     * Persists modifications to an existing user.
     */
    public function save(User $user): void;

    /**
     * @throws \App\Users\Domain\Exception\UserNotFound
     */
    public function byId(UserId $id): User;

    /**
     * @throws \App\Users\Domain\Exception\UserNotFound
     */
    public function byUsername(Username $username): User;

    public function existsWithUsername(Username $username): bool;
}
