<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidPhoneNumber;
use App\Households\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class PhoneNumberTest extends TestCase
{
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
    #[TestDox('Rejects empty input with InvalidPhoneNumber.')]
    public function rejects_empty(string $input): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::of($input);
    }

    #[Test]
    #[TestWith(['555-CALL'])]
    #[TestWith(['+1abc'])]
    #[TestWith(['++1234'])]
    #[TestDox('Rejects input with characters outside digits and a single leading "+".')]
    public function rejects_illegal_characters(string $input): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::of($input);
    }

    #[Test]
    #[TestDox('Rejects a normalized form longer than 32 characters.')]
    public function rejects_overlong(): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::of('+' . str_repeat('1', 33));
    }

    #[Test]
    #[TestDox('Equals another phone with the same normalized value.')]
    public function equals(): void
    {
        self::assertTrue(PhoneNumber::of('555-123-4567')->equals(PhoneNumber::of('5551234567')));
        self::assertFalse(PhoneNumber::of('5551234567')->equals(PhoneNumber::of('5551234568')));
    }
}
