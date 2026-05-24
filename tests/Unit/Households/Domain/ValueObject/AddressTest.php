<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidAddress;
use App\Households\Domain\ValueObject\Address;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class AddressTest extends TestCase
{
    #[Test]
    #[TestDox('Stores trimmed fields and uppercases the country code.')]
    public function stores_trimmed_fields(): void
    {
        $a = Address::of(
            '  123 Main St  ',
            '  Apt 4B  ',
            '  Springfield  ',
            '  IL  ',
            '  62701  ',
            '  us  ',
        );

        self::assertSame('123 Main St', $a->street);
        self::assertSame('Apt 4B', $a->unit);
        self::assertSame('Springfield', $a->city);
        self::assertSame('IL', $a->state);
        self::assertSame('62701', $a->postalCode);
        self::assertSame('US', $a->country);
    }

    #[Test]
    #[TestDox('Normalizes empty-string unit to null.')]
    public function empty_unit_becomes_null(): void
    {
        $a = Address::of('123 Main', '   ', 'Springfield', 'IL', '62701', 'US');

        self::assertNull($a->unit);
    }

    #[Test]
    #[TestWith(['street'])]
    #[TestWith(['city'])]
    #[TestWith(['state'])]
    #[TestWith(['postalCode'])]
    #[TestWith(['country'])]
    #[TestDox('Rejects an empty required field with InvalidAddress.')]
    public function rejects_empty_required_field(string $field): void
    {
        $blank = '   ';
        $street = $field === 'street' ? $blank : '123 Main';
        $city = $field === 'city' ? $blank : 'Springfield';
        $state = $field === 'state' ? $blank : 'IL';
        $postalCode = $field === 'postalCode' ? $blank : '62701';
        $country = $field === 'country' ? $blank : 'US';

        $this->expectException(InvalidAddress::class);

        Address::of($street, null, $city, $state, $postalCode, $country);
    }

    #[Test]
    #[TestWith(['USA'])]
    #[TestWith(['U1'])]
    #[TestWith(['1A'])]
    #[TestDox('Rejects a country that is not ISO 3166-1 alpha-2.')]
    public function rejects_invalid_country(string $country): void
    {
        $this->expectException(InvalidAddress::class);

        Address::of('123 Main', null, 'Springfield', 'IL', '62701', $country);
    }

    #[Test]
    #[TestDox('Equals another Address with identical fields.')]
    public function equals(): void
    {
        $a = Address::of('123 Main', 'Apt 4B', 'Springfield', 'IL', '62701', 'US');
        $b = Address::of('123 Main', 'Apt 4B', 'Springfield', 'IL', '62701', 'US');
        $c = Address::of('124 Main', 'Apt 4B', 'Springfield', 'IL', '62701', 'US');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    #[TestWith(['62701', 'US'])]
    #[TestWith(['12345-6789', 'US'])]
    #[TestDox('Accepts a valid US ZIP (5-digit or ZIP+4).')]
    public function accepts_valid_us_zip(string $postal, string $country): void
    {
        $a = Address::of('123 Main', null, 'Springfield', 'IL', $postal, $country);

        self::assertSame($postal, $a->postalCode);
    }

    #[Test]
    #[TestWith(['ABCDE'])]
    #[TestWith(['1234'])]
    #[TestWith(['123456'])]
    #[TestWith(['12345-678'])]
    #[TestWith(['12345 6789'])]
    #[TestDox('Rejects a malformed US ZIP with InvalidAddress.')]
    public function rejects_invalid_us_zip(string $postal): void
    {
        $this->expectException(InvalidAddress::class);

        Address::of('123 Main', null, 'Springfield', 'IL', $postal, 'US');
    }

    #[Test]
    #[TestDox('Accepts and uppercases a valid Canadian postal code.')]
    public function accepts_valid_ca_postal_code(): void
    {
        $a = Address::of('100 Maple', null, 'Toronto', 'ON', 'k1a 0b1', 'CA');

        self::assertSame('K1A 0B1', $a->postalCode);
    }

    #[Test]
    #[TestWith(['K1A0B1'])]
    #[TestWith(['1K1 0B1'])]
    #[TestWith(['K1A-0B1'])]
    #[TestWith(['ABCDE'])]
    #[TestDox('Rejects a malformed Canadian postal code with InvalidAddress.')]
    public function rejects_invalid_ca_postal_code(string $postal): void
    {
        $this->expectException(InvalidAddress::class);

        Address::of('100 Maple', null, 'Toronto', 'ON', $postal, 'CA');
    }

    #[Test]
    #[TestDox('Accepts a loose GB postal code between 2 and 10 characters.')]
    public function accepts_valid_gb_postal_code(): void
    {
        $a = Address::of('221B Baker St', null, 'London', 'England', 'SW1A 1AA', 'GB');

        self::assertSame('SW1A 1AA', $a->postalCode);
    }

    #[Test]
    #[TestWith(['A'])]
    #[TestWith(['this-is-eleven'])]
    #[TestDox('Rejects a GB postal code outside the 2-10 character range.')]
    public function rejects_invalid_gb_postal_code(string $postal): void
    {
        $this->expectException(InvalidAddress::class);

        Address::of('221B Baker St', null, 'London', 'England', $postal, 'GB');
    }

    #[Test]
    #[TestDox('Accepts any non-empty postal code for non-US/CA/GB countries.')]
    public function accepts_any_non_empty_postal_code_for_other_countries(): void
    {
        $a = Address::of('1 Tokyo St', null, 'Tokyo', 'Tokyo', '100-0001', 'JP');

        self::assertSame('100-0001', $a->postalCode);
    }
}
