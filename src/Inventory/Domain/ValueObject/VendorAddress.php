<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidVendorAddress;
use App\Shared\Domain\ValueObject\PostalCodeRule;

/**
 * Composite postal address for a vendor. Country is an ISO 3166-1 alpha-2
 * code (two uppercase ASCII letters).
 *
 * Per-country postal-code validation:
 *   - US: 5 digits or ZIP+4 (`12345` or `12345-6789`).
 *   - CA: `A1A 1A1` — letter-digit-letter, space, digit-letter-digit.
 *   - GB: 2-10 characters; loose validation only.
 *   - Other countries: any non-empty trimmed string.
 *
 * CA codes are normalised to uppercase; other countries keep the
 * trimmed input as-is.
 *
 * Per-context copy of the Households Address VO: cross-context VO
 * sharing is forbidden.
 */
final readonly class VendorAddress
{
    private const COUNTRY_PATTERN = '/^[A-Z]{2}$/';

    public string $street;
    public ?string $unit;
    public string $city;
    public string $state;
    public string $postalCode;
    public string $country;

    private function __construct(
        string $street,
        ?string $unit,
        string $city,
        string $state,
        string $postalCode,
        string $country,
    ) {
        $this->street = $street;
        $this->unit = $unit;
        $this->city = $city;
        $this->state = $state;
        $this->postalCode = $postalCode;
        $this->country = $country;
    }

    public static function of(
        string $street,
        ?string $unit,
        string $city,
        string $state,
        string $postalCode,
        string $country,
    ): self {
        $streetT = trim($street);
        if ($streetT === '') {
            throw InvalidVendorAddress::emptyField('street');
        }

        $unitT = $unit !== null ? trim($unit) : null;
        if ($unitT === '') {
            $unitT = null;
        }

        $cityT = trim($city);
        if ($cityT === '') {
            throw InvalidVendorAddress::emptyField('city');
        }

        $stateT = trim($state);
        if ($stateT === '') {
            throw InvalidVendorAddress::emptyField('state');
        }

        $postalT = trim($postalCode);
        if ($postalT === '') {
            throw InvalidVendorAddress::emptyField('postalCode');
        }

        $countryT = trim($country);
        if ($countryT === '') {
            throw InvalidVendorAddress::emptyField('country');
        }

        $countryUp = strtoupper($countryT);
        if (preg_match(self::COUNTRY_PATTERN, $countryUp) !== 1) {
            throw InvalidVendorAddress::invalidCountry($countryUp);
        }

        $postalNormalised = self::validatePostalCode($postalT, $countryUp);

        return new self($streetT, $unitT, $cityT, $stateT, $postalNormalised, $countryUp);
    }

    /**
     * Validates the postal code against the per-country rules and returns
     * the normalised form for storage.
     */
    private static function validatePostalCode(string $postal, string $country): string
    {
        $normalised = PostalCodeRule::normalize($postal, $country);
        if ($normalised === null) {
            throw InvalidVendorAddress::invalidPostalCode($country);
        }

        return $normalised;
    }

    public function equals(self $other): bool
    {
        return $this->street === $other->street
            && $this->unit === $other->unit
            && $this->city === $other->city
            && $this->state === $other->state
            && $this->postalCode === $other->postalCode
            && $this->country === $other->country;
    }
}
