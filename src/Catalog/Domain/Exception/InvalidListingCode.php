<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use DomainException;

final class InvalidListingCode extends DomainException implements CatalogDomainException
{
    public const MAX_LENGTH = 32;

    public static function empty(): self
    {
        return new self('A listing code must not be empty.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Listing code length %d exceeds the maximum of %d characters.',
            $length,
            self::MAX_LENGTH,
        ));
    }

    public static function illegalCharacters(string $value): self
    {
        return new self(sprintf(
            'Listing code "%s" contains characters outside the allowed set [A-Z0-9_-].',
            $value,
        ));
    }
}
