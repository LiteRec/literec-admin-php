<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRankId;
use App\Administration\Domain\ValueObject\RankId;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class RankIdTest extends TestCase
{
    private const string VALID_UUID_V7 = '019571bf-5d51-7000-b500-00000000ca01';

    #[Test]
    #[TestDox('fromString() accepts a canonical UUID v7 and round-trips its value.')]
    public function from_string_accepts_a_valid_uuid_v7(): void
    {
        $id = RankId::fromString(self::VALID_UUID_V7);

        self::assertSame(self::VALID_UUID_V7, $id->value);
        self::assertSame(self::VALID_UUID_V7, (string) $id);
    }

    #[Test]
    #[TestWith([''], 'empty string')]
    #[TestWith(['not-a-uuid'], 'not a UUID')]
    #[TestWith(['019571bf-5d51-4000-b500-00000000ca01'], 'UUID v4, not v7')]
    #[TestDox('fromString() rejects a value that is not a canonical UUID v7 with InvalidRankId.')]
    public function from_string_rejects_invalid_values(string $value): void
    {
        $this->expectException(InvalidRankId::class);

        self::assertInstanceOf(RankId::class, RankId::fromString($value));
    }

    #[Test]
    #[TestDox('equals() compares by value.')]
    public function equals_compares_by_value(): void
    {
        $a = RankId::fromString(self::VALID_UUID_V7);
        $b = RankId::fromString(self::VALID_UUID_V7);
        $c = RankId::fromString('019571bf-5d51-7000-b500-00000000ca02');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
