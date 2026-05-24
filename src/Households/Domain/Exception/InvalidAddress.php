<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidAddress extends DomainException implements HouseholdsDomainException
{
    public static function emptyField(string $field): self
    {
        return new self(sprintf('Address field "%s" must not be empty.', $field));
    }

    public static function invalidCountry(string $iso): self
    {
        return new self(sprintf(
            'Address country "%s" is not a valid ISO 3166-1 alpha-2 code.',
            $iso,
        ));
    }

    public static function invalidPostalCode(string $country): self
    {
        return new self(sprintf(
            'Address postal code is not valid for country "%s".',
            $country,
        ));
    }
}
