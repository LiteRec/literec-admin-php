<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidPersonName extends DomainException implements HouseholdsDomainException
{
    public static function emptyFirstName(): self
    {
        return new self('A person name must have a non-empty first name.');
    }

    public static function emptyLastName(): self
    {
        return new self('A person name must have a non-empty last name.');
    }
}
