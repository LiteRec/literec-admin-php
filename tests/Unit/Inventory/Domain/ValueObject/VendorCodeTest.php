<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidVendorCode;
use App\Inventory\Domain\ValueObject\VendorCode;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class VendorCodeTest extends TestCase
{
    /**
     * @return Generator<string, array{string}>
     */
    public static function validCases(): Generator
    {
        yield 'single uppercase letter'   => ['A'];
        yield 'single digit'              => ['7'];
        yield 'mixed alnum'               => ['ACME01'];
        yield 'with underscore'           => ['ACME_NW'];
        yield 'with hyphen'               => ['ACME-NW'];
        yield 'thirty-two char maximum'   => ['ACME0123456789012345678901234567'];
    }

    #[Test]
    #[DataProvider('validCases')]
    #[TestDox('Accepts a valid vendor code: $_dataName.')]
    public function accepts_a_valid_vendor_code(string $value): void
    {
        self::assertSame($value, VendorCode::fromString($value)->value);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function invalidCases(): Generator
    {
        yield 'empty'                 => [''];
        yield 'thirty-three char too long' => ['ACME01234567890123456789012345678'];
        yield 'lowercase rejected'    => ['acme'];
        yield 'starts with hyphen'    => ['-ACME'];
        yield 'starts with underscore' => ['_ACME'];
        yield 'contains space'        => ['AC ME'];
        yield 'contains punctuation'  => ['ACME!'];
    }

    #[Test]
    #[DataProvider('invalidCases')]
    #[TestDox('Rejects an invalid vendor code: $_dataName.')]
    public function rejects_invalid_vendor_code(string $value): void
    {
        $this->expectException(InvalidVendorCode::class);

        VendorCode::fromString($value);
    }

    #[Test]
    #[TestDox('Stringifies to the canonical value.')]
    public function stringifies_to_the_canonical_value(): void
    {
        self::assertSame('ACME01', (string) VendorCode::fromString('ACME01'));
    }

    #[Test]
    #[TestDox('equals() compares by string value.')]
    public function equals_compares_by_string_value(): void
    {
        $a = VendorCode::fromString('ACME01');
        $b = VendorCode::fromString('ACME01');
        $c = VendorCode::fromString('ACME02');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
