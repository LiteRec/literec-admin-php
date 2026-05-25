<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use DomainException;

final class InvalidFee extends DomainException implements CatalogDomainException
{
    public const MAX_LABEL_LENGTH = 64;

    public static function emptyLabel(): self
    {
        return new self('A fee must carry a non-empty label.');
    }

    public static function labelTooLong(int $length): self
    {
        return new self(sprintf(
            'Fee label length %d exceeds the maximum of %d characters.',
            $length,
            self::MAX_LABEL_LENGTH,
        ));
    }
}
