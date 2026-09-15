<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidProfilePhoto extends DomainException implements HouseholdsDomainException
{
    public static function emptyStorageKey(): self
    {
        return new self('A profile photo storage key must be a non-empty string.');
    }

    /**
     * @param string $key Not embedded in the message — the key can carry
     *                    directory-traversal segments and should not leak
     *                    into logs verbatim.
     */
    public static function illegalCharacters(string $key): self
    {
        unset($key);

        return new self('Profile photo storage key contains characters outside [A-Za-z0-9_-./].');
    }

    /**
     * @param string $key Not embedded in the message — see
     *                    {@see self::illegalCharacters()}.
     */
    public static function pathTraversal(string $key): self
    {
        unset($key);

        return new self('Profile photo storage key must not contain ".." path segments.');
    }
}
