<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidTransactionReference extends DomainException implements HouseholdsDomainException
{
    public static function empty(): self
    {
        return new self('A transaction reference must be a non-empty string.');
    }
}
