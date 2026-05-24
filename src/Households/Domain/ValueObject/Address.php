<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidAddress;

/**
 * Composite postal address. Country is an ISO 3166-1 alpha-2 code
 * (two uppercase ASCII letters). Per-country postal-code validation is
 * out of scope for this VO and lives in a downstream ticket.
 */
final readonly class Address
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
            throw InvalidAddress::emptyField('street');
        }

        $unitT = $unit !== null ? trim($unit) : null;
        if ($unitT === '') {
            $unitT = null;
        }

        $cityT = trim($city);
        if ($cityT === '') {
            throw InvalidAddress::emptyField('city');
        }

        $stateT = trim($state);
        if ($stateT === '') {
            throw InvalidAddress::emptyField('state');
        }

        $postalT = trim($postalCode);
        if ($postalT === '') {
            throw InvalidAddress::emptyField('postalCode');
        }

        $countryT = trim($country);
        if ($countryT === '') {
            throw InvalidAddress::emptyField('country');
        }

        $countryUp = strtoupper($countryT);
        if (preg_match(self::COUNTRY_PATTERN, $countryUp) !== 1) {
            throw InvalidAddress::invalidCountry($countryUp);
        }

        return new self($streetT, $unitT, $cityT, $stateT, $postalT, $countryUp);
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
