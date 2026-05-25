<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Identity;

use App\Inventory\Infrastructure\Identity\Uuid7IdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class Uuid7IdentityGeneratorTest extends TestCase
{
    private const UUID_V7_PATTERN
        = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    #[Test]
    #[TestDox('Generates a valid UUID v7 for every Inventory identity type.')]
    public function generates_a_valid_uuid_v7_for_every_inventory_identity_type(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $generator = new Uuid7IdentityGenerator($clock);

        self::assertMatchesRegularExpression(self::UUID_V7_PATTERN, $generator->nextInventoryItemId()->value);
        self::assertMatchesRegularExpression(self::UUID_V7_PATTERN, $generator->nextStockBatchId()->value);
        self::assertMatchesRegularExpression(self::UUID_V7_PATTERN, $generator->nextStockMovementId()->value);
        self::assertMatchesRegularExpression(self::UUID_V7_PATTERN, $generator->nextVendorId()->value);
    }

    #[Test]
    #[TestDox('Returns lexicographically sortable identifiers when the clock advances.')]
    public function returns_lexicographically_sortable_ids_when_clock_advances(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $generator = new Uuid7IdentityGenerator($clock);

        $values = [];
        for ($i = 0; $i < 8; $i++) {
            $values[] = $generator->nextInventoryItemId()->value;
            $clock->sleep(0.001);
        }

        $sorted = $values;
        sort($sorted);

        self::assertSame($values, $sorted);
    }

    #[Test]
    #[TestDox('Returns unique identifiers across many calls at the same instant.')]
    public function returns_unique_ids_across_many_calls(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $generator = new Uuid7IdentityGenerator($clock);

        $values = [];
        for ($i = 0; $i < 1000; $i++) {
            $values[] = $generator->nextInventoryItemId()->value;
        }

        self::assertCount(1000, array_unique($values));
    }
}
