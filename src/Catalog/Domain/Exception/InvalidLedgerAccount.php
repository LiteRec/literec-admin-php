<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use DomainException;

final class InvalidLedgerAccount extends DomainException implements CatalogDomainException
{
    public const MIN_LENGTH = 4;
    public const MAX_LENGTH = 16;

    public static function badLength(int $length): self
    {
        return new self(sprintf(
            'Ledger account code length %d must be between %d and %d characters.',
            $length,
            self::MIN_LENGTH,
            self::MAX_LENGTH,
        ));
    }

    public static function illegalCharacters(string $value): self
    {
        return new self(sprintf(
            'Ledger account code "%s" contains characters outside the allowed set [A-Z0-9-].',
            $value,
        ));
    }
}
