<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\ValueObject\HouseholdId;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class HouseholdIdTest extends TestCase
{
    /**
     * @return Generator<string, array{string}>
     */
    public static function validUuidV7Cases(): Generator
    {
        yield 'all-lowercase canonical'      => ['019571bf-5d51-7000-b500-0123456789ab'];
        yield 'with leading zeros'           => ['00000000-0000-7000-8000-000000000000'];
        yield 'variant nibble 9'             => ['019571bf-5d51-7abc-9def-0123456789ab'];
        yield 'variant nibble a'             => ['019571bf-5d51-7abc-a012-0123456789ab'];
        yield 'variant nibble b (maximum)'   => ['ffffffff-ffff-7fff-bfff-ffffffffffff'];
    }

    #[Test]
    #[DataProvider('validUuidV7Cases')]
    #[TestDox('Accepts a valid UUID v7 string in canonical RFC 4122 form: $_dataName.')]
    public function accepts_a_valid_uuid_v7_string(string $value): void
    {
        self::assertSame($value, HouseholdId::fromString($value)->value);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function invalidUuidCases(): Generator
    {
        yield 'empty string'               => [''];
        yield 'plain text'                 => ['not-a-uuid'];
        yield 'uuid v4 (version nibble 4)' => ['12345678-1234-4abc-9def-0123456789ab'];
        yield 'uppercase'                  => ['019571BF-5D51-7000-B500-0123456789AB'];
        yield 'missing hyphen'             => ['019571bf5d517000b5000123456789ab'];
        yield 'invalid variant nibble c'   => ['019571bf-5d51-7abc-c012-0123456789ab'];
    }

    #[Test]
    #[DataProvider('invalidUuidCases')]
    #[TestDox('Rejects an invalid UUID v7 with InvalidHouseholdId: $_dataName.')]
    public function rejects_invalid_uuid_v7(string $value): void
    {
        $this->expectException(InvalidHouseholdId::class);

        HouseholdId::fromString($value);
    }

    #[Test]
    #[TestDox('Stringifies to the stored value.')]
    public function stringifies_to_the_stored_value(): void
    {
        $value = '019571bf-5d51-7000-b500-0123456789ab';

        self::assertSame($value, (string) HouseholdId::fromString($value));
    }

    #[Test]
    #[TestDox('Equals another HouseholdId with the same value and not-equals when values differ.')]
    public function equals_an_identical_id(): void
    {
        $a = HouseholdId::fromString('019571bf-5d51-7000-b500-0123456789ab');
        $b = HouseholdId::fromString('019571bf-5d51-7000-b500-0123456789ab');
        $c = HouseholdId::fromString('019571c0-0000-7000-8000-000000000000');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
