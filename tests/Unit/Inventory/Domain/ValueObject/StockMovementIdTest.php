<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidStockMovementId;
use App\Inventory\Domain\ValueObject\StockMovementId;
use App\Tests\Support\Trait\UuidV7TestCases;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class StockMovementIdTest extends TestCase
{
    use UuidV7TestCases;

    #[Test]
    #[DataProvider('validUuidV7Cases')]
    #[TestDox('Accepts a valid UUID v7 string: $_dataName.')]
    public function accepts_a_valid_uuid_v7_string(string $value): void
    {
        self::assertSame($value, StockMovementId::fromString($value)->value);
    }

    #[Test]
    #[DataProvider('invalidUuidCases')]
    #[TestDox('Rejects an invalid UUID v7 with InvalidStockMovementId: $_dataName.')]
    public function rejects_invalid_uuid_v7(string $value): void
    {
        $this->expectException(InvalidStockMovementId::class);

        StockMovementId::fromString($value);
    }

    #[Test]
    #[TestDox('Stringifies to the stored value.')]
    public function stringifies_to_the_stored_value(): void
    {
        $value = '019571bf-5d51-7000-b500-0123456789ab';

        self::assertSame($value, (string) StockMovementId::fromString($value));
    }

    #[Test]
    #[TestDox('Equals another id with the same value and not-equals when values differ.')]
    public function equals_an_identical_id_and_not_equals_a_different_one(): void
    {
        $a = StockMovementId::fromString('019571bf-5d51-7000-b500-0123456789ab');
        $b = StockMovementId::fromString('019571bf-5d51-7000-b500-0123456789ab');
        $c = StockMovementId::fromString('019571c0-0000-7000-8000-000000000000');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
