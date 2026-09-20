<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\RevocationReason;
use DomainException;

final class InvalidRevocationReason extends DomainException implements AdministrationDomainException
{
    public static function empty(): self
    {
        return new self('Revocation reason must not be empty.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Revocation reason is %d characters; the maximum is %d.',
            $length,
            RevocationReason::MAX_LENGTH,
        ));
    }
}
