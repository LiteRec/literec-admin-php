<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidAdministratorId;
use App\Administration\Domain\ValueObject\AdministratorId;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class AdministratorIdTest extends TestCase
{
    private const string VALID_UUID_V7 = '019571bf-5d51-7000-b500-00000000ab01';

    #[Test]
    #[TestDox('fromString() accepts a canonical UUID v7 and round-trips its value.')]
    public function from_string_accepts_a_valid_uuid_v7(): void
    {
        $id = AdministratorId::fromString(self::VALID_UUID_V7);

        self::assertSame(self::VALID_UUID_V7, $id->value);
        self::assertSame(self::VALID_UUID_V7, (string) $id);
    }

    #[Test]
    #[TestDox('fromString() rejects a value that is not a canonical UUID v7 with InvalidAdministratorId.')]
    public function from_string_rejects_invalid_values(): void
    {
        $this->expectException(InvalidAdministratorId::class);

        self::assertInstanceOf(AdministratorId::class, AdministratorId::fromString('not-a-uuid'));
    }

    #[Test]
    #[TestDox('equals() compares by value.')]
    public function equals_compares_by_value(): void
    {
        $a = AdministratorId::fromString(self::VALID_UUID_V7);
        $b = AdministratorId::fromString(self::VALID_UUID_V7);
        $c = AdministratorId::fromString('019571bf-5d51-7000-b500-00000000ab02');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
