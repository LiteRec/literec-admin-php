<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use DomainException;

final class InvalidListingName extends DomainException implements CatalogDomainException
{
    public const MAX_LENGTH = 255;

    public static function empty(): self
    {
        return new self('A listing name must not be empty.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Listing name length %d exceeds the maximum of %d characters.',
            $length,
            self::MAX_LENGTH,
        ));
    }
}
