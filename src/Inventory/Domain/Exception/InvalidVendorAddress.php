<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidVendorAddress extends DomainException implements InventoryDomainException
{
    public static function emptyField(string $field): self
    {
        return new self(sprintf('Vendor address field "%s" must not be empty.', $field));
    }

    public static function invalidCountry(string $iso): self
    {
        return new self(sprintf(
            'Vendor address country "%s" is not a valid ISO 3166-1 alpha-2 code.',
            $iso,
        ));
    }

    public static function invalidPostalCode(string $country): self
    {
        return new self(sprintf(
            'Vendor address postal code is not valid for country "%s".',
            $country,
        ));
    }
}
