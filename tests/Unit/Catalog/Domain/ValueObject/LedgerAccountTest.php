<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidLedgerAccount;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class LedgerAccountTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function validCases(): Generator
    {
        yield 'min length 4'  => ['4000', '4000'];
        yield 'with dashes'   => ['4000-INV', '4000-INV'];
        yield 'lowercased input is uppercased' => ['4000-inv', '4000-INV'];
        yield 'whitespace trimmed' => ['  4000-INV  ', '4000-INV'];
        yield 'max length 16' => [str_repeat('4', 16), str_repeat('4', 16)];
    }

    #[Test]
    #[DataProvider('validCases')]
    #[TestDox('Normalizes valid input: $_dataName.')]
    public function normalizes_valid_input(string $input, string $expected): void
    {
        self::assertSame($expected, LedgerAccount::of($input)->value);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function invalidLengthCases(): Generator
    {
        yield 'too short (3 chars)' => ['400'];
        yield 'empty'               => [''];
        yield 'too long (17 chars)' => [str_repeat('4', 17)];
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function illegalCharacterCases(): Generator
    {
        yield 'underscore' => ['4000_INV'];
        yield 'space'      => ['4000 INV'];
        yield 'slash'      => ['4000/INV'];
        yield 'period'     => ['4000.INV'];
    }

    #[Test]
    #[DataProvider('invalidLengthCases')]
    #[DataProvider('illegalCharacterCases')]
    #[TestDox('Rejects invalid input (bad length or characters outside [A-Z0-9-]): $_dataName.')]
    public function rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidLedgerAccount::class);

        LedgerAccount::of($value);
    }

    #[Test]
    #[TestDox('Equals another LedgerAccount with the same canonical value.')]
    public function equals_compares_canonical_value(): void
    {
        $a = LedgerAccount::of('4000-inv');
        $b = LedgerAccount::of('4000-INV');
        $c = LedgerAccount::of('5000-INV');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    #[TestDox('Stringifies to its canonical value.')]
    public function stringifies(): void
    {
        self::assertSame('4000-INV', (string) LedgerAccount::of('4000-inv'));
    }
}
