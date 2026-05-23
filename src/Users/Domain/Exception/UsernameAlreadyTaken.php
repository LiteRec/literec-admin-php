<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use DomainException;

final class UsernameAlreadyTaken extends DomainException implements UsersDomainException
{
    /**
     * Takes the username string. LRA-15 tightens this signature to accept a
     * Username value object once that VO exists.
     */
    public static function for(string $username): self
    {
        return new self(sprintf('Username "%s" is already taken.', $username));
    }
}
