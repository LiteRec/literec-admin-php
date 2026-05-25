<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidVendorAddress;
use App\Inventory\Domain\ValueObject\VendorAddress;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class VendorAddressTest extends TestCase
{
    #[Test]
    #[TestDox('Accepts a valid US address and preserves the ZIP format.')]
    public function accepts_us_address(): void
    {
        $a = VendorAddress::of('123 Main St', 'Suite 4', 'Springfield', 'IL', '62701-1234', 'us');

        self::assertSame('123 Main St', $a->street);
        self::assertSame('Suite 4', $a->unit);
        self::assertSame('Springfield', $a->city);
        self::assertSame('IL', $a->state);
        self::assertSame('62701-1234', $a->postalCode);
        self::assertSame('US', $a->country);
    }

    #[Test]
    #[TestDox('Normalizes CA postal codes to uppercase.')]
    public function normalizes_ca_postal_code(): void
    {
        $a = VendorAddress::of('1 Yonge St', null, 'Toronto', 'ON', 'm5e 1w7', 'ca');

        self::assertSame('M5E 1W7', $a->postalCode);
    }

    #[Test]
    #[TestDox('Accepts a permissive GB postal code (loose length-only validation).')]
    public function accepts_gb_address(): void
    {
        $a = VendorAddress::of('221B Baker St', null, 'London', 'England', 'NW1 6XE', 'gb');

        self::assertSame('NW1 6XE', $a->postalCode);
        self::assertSame('GB', $a->country);
    }

    #[Test]
    #[TestDox('Treats an empty unit string as null after trimming.')]
    public function empty_unit_becomes_null(): void
    {
        $a = VendorAddress::of('1 Main', '  ', 'Springfield', 'IL', '62701', 'US');

        self::assertNull($a->unit);
    }

    /**
     * @return Generator<string, array{string, ?string, string, string, string, string}>
     */
    public static function invalidCases(): Generator
    {
        yield 'empty street'        => ['', null, 'Springfield', 'IL', '62701', 'US'];
        yield 'empty city'          => ['1 Main', null, '', 'IL', '62701', 'US'];
        yield 'empty state'         => ['1 Main', null, 'Springfield', '', '62701', 'US'];
        yield 'empty postal code'   => ['1 Main', null, 'Springfield', 'IL', '', 'US'];
        yield 'empty country'       => ['1 Main', null, 'Springfield', 'IL', '62701', ''];
        yield 'invalid country code' => ['1 Main', null, 'Springfield', 'IL', '62701', 'USA'];
        yield 'invalid US zip'       => ['1 Main', null, 'Springfield', 'IL', '627', 'US'];
        yield 'invalid CA postal'    => ['1 Yonge St', null, 'Toronto', 'ON', '12345', 'CA'];
        yield 'invalid GB postal'    => ['221B Baker St', null, 'London', 'England', 'X', 'GB'];
    }

    #[Test]
    #[DataProvider('invalidCases')]
    #[TestDox('Rejects an invalid address: $_dataName.')]
    public function rejects_invalid_address(
        string $street,
        ?string $unit,
        string $city,
        string $state,
        string $postalCode,
        string $country,
    ): void {
        $this->expectException(InvalidVendorAddress::class);

        VendorAddress::of($street, $unit, $city, $state, $postalCode, $country);
    }

    #[Test]
    #[TestDox('equals() compares every field including the normalized postal code and country.')]
    public function equals_compares_all_fields(): void
    {
        $a = VendorAddress::of('1 Main', null, 'Springfield', 'IL', '62701', 'US');
        $b = VendorAddress::of('1 Main', null, 'Springfield', 'IL', '62701', 'us');
        $c = VendorAddress::of('2 Main', null, 'Springfield', 'IL', '62701', 'US');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
