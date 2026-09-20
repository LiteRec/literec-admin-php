<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRoleId;
use App\Administration\Domain\ValueObject\RoleId;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class RoleIdTest extends TestCase
{
    private const string VALID_UUID_V7 = '019571bf-5d51-7000-b500-00000000aa01';

    #[Test]
    #[TestDox('fromString() accepts a canonical UUID v7 and round-trips its value.')]
    public function from_string_accepts_a_valid_uuid_v7(): void
    {
        $id = RoleId::fromString(self::VALID_UUID_V7);

        self::assertSame(self::VALID_UUID_V7, $id->value);
        self::assertSame(self::VALID_UUID_V7, (string) $id);
    }

    #[Test]
    #[TestWith([''], 'empty string')]
    #[TestWith(['not-a-uuid'], 'not a UUID')]
    #[TestWith(['019571bf-5d51-4000-b500-00000000aa01'], 'UUID v4, not v7')]
    #[TestDox('fromString() rejects a value that is not a canonical UUID v7 with InvalidRoleId.')]
    public function from_string_rejects_invalid_values(string $value): void
    {
        $this->expectException(InvalidRoleId::class);

        self::assertInstanceOf(RoleId::class, RoleId::fromString($value));
    }

    #[Test]
    #[TestDox('equals() compares by value.')]
    public function equals_compares_by_value(): void
    {
        $a = RoleId::fromString(self::VALID_UUID_V7);
        $b = RoleId::fromString(self::VALID_UUID_V7);
        $c = RoleId::fromString('019571bf-5d51-7000-b500-00000000aa02');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
