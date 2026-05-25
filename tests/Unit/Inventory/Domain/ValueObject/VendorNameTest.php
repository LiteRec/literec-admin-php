<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidVendorName;
use App\Inventory\Domain\ValueObject\VendorName;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class VendorNameTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function validCases(): Generator
    {
        yield 'single character'        => ['A', 'A'];
        yield 'typical vendor name'     => ['Acme Supply Co.', 'Acme Supply Co.'];
        yield 'trims surrounding space' => ['  Acme  ', 'Acme'];
        yield 'one-hundred char maximum' => [str_repeat('a', 100), str_repeat('a', 100)];
    }

    #[Test]
    #[DataProvider('validCases')]
    #[TestDox('Accepts a valid vendor name: $_dataName.')]
    public function accepts_a_valid_vendor_name(string $input, string $expected): void
    {
        self::assertSame($expected, VendorName::of($input)->value);
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
    #[TestDox('Rejects an invalid vendor name: $_dataName.')]
    public function rejects_invalid_vendor_name(string $value): void
    {
        $this->expectException(InvalidVendorName::class);

        VendorName::of($value);
    }

    #[Test]
    #[TestDox('Stringifies to the canonical value.')]
    public function stringifies_to_the_canonical_value(): void
    {
        self::assertSame('Acme', (string) VendorName::of('Acme'));
    }

    #[Test]
    #[TestDox('equals() compares by string value.')]
    public function equals_compares_by_string_value(): void
    {
        $a = VendorName::of('Acme');
        $b = VendorName::of('Acme');
        $c = VendorName::of('Other');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
