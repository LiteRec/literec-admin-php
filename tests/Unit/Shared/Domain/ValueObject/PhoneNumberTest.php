<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidPhoneNumber;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class PhoneNumberTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function validCases(): Generator
    {
        yield 'digits only'              => ['5551234567', '5551234567'];
        yield 'strips parens hyphens'    => ['(555) 123-4567', '5551234567'];
        yield 'preserves leading plus'   => ['+1 555 123 4567', '+15551234567'];
        yield 'trims surrounding space'  => ['  5551234567  ', '5551234567'];
    }

    #[Test]
    #[DataProvider('validCases')]
    #[TestDox('Accepts a valid phone number: $_dataName.')]
    public function accepts_a_valid_phone(string $input, string $expected): void
    {
        self::assertSame($expected, PhoneNumber::of($input)->value);
    }

    #[Test]
    #[TestWith(['+1 (555) 123-4567', '+15551234567'])]
    #[TestWith(['555 123 4567', '5551234567'])]
    #[TestWith(['+44-20-7946-0958', '+442079460958'])]
    #[TestDox('Strips whitespace/parens/hyphens, preserves leading "+", and stores the normalized form.')]
    public function normalizes_input(string $input, string $expected): void
    {
        self::assertSame($expected, PhoneNumber::of($input)->value);
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(['+'])]
    #[TestDox('Rejects empty input (including a lone "+") with InvalidPhoneNumber::empty().')]
    public function rejects_empty(string $input): void
    {
        $this->expectException(InvalidPhoneNumber::class);
        $this->expectExceptionMessageMatches('/must not be empty/');

        PhoneNumber::of($input);
    }

    #[Test]
    #[TestWith(['555-CALL'])]
    #[TestWith(['+1abc'])]
    #[TestWith(['++1234'])]
    #[TestWith(['555-CALL-NOW'])]
    #[TestDox('Rejects input with characters outside digits and a single leading "+".')]
    public function rejects_illegal_characters(string $input): void
    {
        $this->expectException(InvalidPhoneNumber::class);
        $this->expectExceptionMessageMatches('/characters other than digits/');

        PhoneNumber::of($input);
    }

    #[Test]
    #[TestWith(['+111111111111111111111111111111111'])]
    #[TestWith(['+1111111111111111111111111111111111'])]
    #[TestDox('Rejects a normalized form longer than 32 characters.')]
    public function rejects_overlong(string $input): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::of($input);
    }

    #[Test]
    #[TestDox('Equals another phone with the same normalized value.')]
    public function equals(): void
    {
        self::assertTrue(PhoneNumber::of('555-123-4567')->equals(PhoneNumber::of('5551234567')));
        self::assertFalse(PhoneNumber::of('5551234567')->equals(PhoneNumber::of('5551234568')));
        self::assertTrue(
            PhoneNumber::of('(555) 123-4567')
                ->equals(PhoneNumber::of('5551234567')),
        );
    }
}
