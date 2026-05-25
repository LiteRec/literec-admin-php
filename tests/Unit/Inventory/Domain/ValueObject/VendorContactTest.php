<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidVendorContact;
use App\Inventory\Domain\ValueObject\VendorContact;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class VendorContactTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function validCases(): Generator
    {
        yield 'single character'        => ['A', 'A'];
        yield 'first and last name'     => ['Jane Smith', 'Jane Smith'];
        yield 'trims surrounding space' => ['  Jane Smith  ', 'Jane Smith'];
        yield 'one-hundred char maximum' => [str_repeat('a', 100), str_repeat('a', 100)];
    }

    #[Test]
    #[DataProvider('validCases')]
    #[TestDox('Accepts a valid vendor contact: $_dataName.')]
    public function accepts_a_valid_vendor_contact(string $input, string $expected): void
    {
        self::assertSame($expected, VendorContact::of($input)->value);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function invalidCases(): Generator
    {
        yield 'empty'              => [''];
        yield 'whitespace only'    => ['   '];
        yield 'one-hundred-one char' => [str_repeat('a', 101)];
    }

    #[Test]
    #[DataProvider('invalidCases')]
    #[TestDox('Rejects an invalid vendor contact: $_dataName.')]
    public function rejects_invalid_vendor_contact(string $value): void
    {
        $this->expectException(InvalidVendorContact::class);

        VendorContact::of($value);
    }

    #[Test]
    #[TestDox('Stringifies to the canonical value.')]
    public function stringifies_to_the_canonical_value(): void
    {
        self::assertSame('Jane Smith', (string) VendorContact::of('Jane Smith'));
    }

    #[Test]
    #[TestDox('equals() compares by string value.')]
    public function equals_compares_by_string_value(): void
    {
        $a = VendorContact::of('Jane Smith');
        $b = VendorContact::of('Jane Smith');
        $c = VendorContact::of('John Doe');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
