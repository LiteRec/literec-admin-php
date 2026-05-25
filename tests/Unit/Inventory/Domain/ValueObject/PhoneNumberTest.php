<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidPhoneNumber;
use App\Inventory\Domain\ValueObject\PhoneNumber;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class PhoneNumberTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function validCases(): Generator
    {
        yield 'digits only'           => ['5551234567', '5551234567'];
        yield 'strips parens hyphens' => ['(555) 123-4567', '5551234567'];
        yield 'preserves leading plus' => ['+1 555 123 4567', '+15551234567'];
        yield 'trims surrounding space' => ['  5551234567  ', '5551234567'];
    }

    #[Test]
    #[DataProvider('validCases')]
    #[TestDox('Accepts a valid phone number: $_dataName.')]
    public function accepts_a_valid_phone(string $input, string $expected): void
    {
        self::assertSame($expected, PhoneNumber::of($input)->value);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function invalidCases(): Generator
    {
        yield 'empty'                  => [''];
        yield 'whitespace only'        => ['   '];
        yield 'letters'                => ['555-CALL-NOW'];
        yield 'plus only'              => ['+'];
        yield 'too long'               => ['+' . str_repeat('1', 32)];
    }

    #[Test]
    #[DataProvider('invalidCases')]
    #[TestDox('Rejects an invalid phone number: $_dataName.')]
    public function rejects_invalid_phone(string $value): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::of($value);
    }

    #[Test]
    #[TestDox('equals() compares by normalized value.')]
    public function equals_compares_by_normalized_value(): void
    {
        self::assertTrue(
            PhoneNumber::of('(555) 123-4567')
                ->equals(PhoneNumber::of('5551234567')),
        );
        self::assertFalse(
            PhoneNumber::of('5551234567')
                ->equals(PhoneNumber::of('5551234568')),
        );
    }
}
