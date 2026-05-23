<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use DomainException;

final class UserNotFound extends DomainException implements UsersDomainException
{
    /**
     * Takes the user id string. LRA-15 tightens this signature to accept a
     * UserId value object once that VO exists.
     */
    public static function byId(string $id): self
    {
        return new self(sprintf('No user exists with id "%s".', $id));
    }

    /**
     * Takes the username string. LRA-15 tightens this signature to accept a
     * Username value object once that VO exists.
     */
    public static function byUsername(string $username): self
    {
        return new self(sprintf('No user exists with username "%s".', $username));
    }
}
