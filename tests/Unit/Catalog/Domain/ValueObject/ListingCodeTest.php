<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidListingCode;
use App\Catalog\Domain\ValueObject\ListingCode;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class ListingCodeTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function validCases(): Generator
    {
        yield 'already canonical'      => ['ABC-1', 'ABC-1'];
        yield 'lowercase is uppercased' => ['abc-1', 'ABC-1'];
        yield 'surrounding whitespace'  => ['  ABC_1  ', 'ABC_1'];
        yield 'max length 32'           => [str_repeat('A', 32), str_repeat('A', 32)];
        yield 'digits and underscores'  => ['SKU_001', 'SKU_001'];
    }

    #[Test]
    #[DataProvider('validCases')]
    #[TestDox('Normalizes valid input to uppercase and trims whitespace: $_dataName.')]
    public function normalizes_valid_input(string $input, string $expected): void
    {
        self::assertSame($expected, ListingCode::of($input)->value);
    }

    #[Test]
    #[TestDox('Rejects an empty (or whitespace-only) code with InvalidListingCode.')]
    public function rejects_empty_code(): void
    {
        $this->expectException(InvalidListingCode::class);

        ListingCode::of('   ');
    }

    #[Test]
    #[TestDox('Rejects a code longer than 32 characters with InvalidListingCode.')]
    public function rejects_overlong_code(): void
    {
        $this->expectException(InvalidListingCode::class);

        ListingCode::of(str_repeat('A', 33));
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function illegalCharacterCases(): Generator
    {
        yield 'space inside'  => ['ABC 1'];
        yield 'emoji character' => ['ABC🎁'];
        yield 'plus sign'     => ['ABC+1'];
        yield 'forward slash' => ['ABC/1'];
        yield 'period'        => ['ABC.1'];
    }

    #[Test]
    #[DataProvider('illegalCharacterCases')]
    #[TestDox('Rejects characters outside [A-Z0-9_-]: $_dataName.')]
    public function rejects_illegal_characters(string $value): void
    {
        $this->expectException(InvalidListingCode::class);

        ListingCode::of($value);
    }

    #[Test]
    #[TestDox('Equals another ListingCode with the same canonical value (case-insensitive on input).')]
    public function equals_case_insensitively(): void
    {
        $a = ListingCode::of('abc-1');
        $b = ListingCode::of('ABC-1');
        $c = ListingCode::of('ABC-2');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    #[TestDox('Stringifies to its canonical value.')]
    public function stringifies(): void
    {
        self::assertSame('ABC-1', (string) ListingCode::of(' abc-1 '));
    }
}
