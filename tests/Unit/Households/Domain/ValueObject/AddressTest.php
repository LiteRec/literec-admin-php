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
}
