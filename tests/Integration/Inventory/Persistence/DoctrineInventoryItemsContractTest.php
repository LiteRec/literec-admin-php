<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Persistence;

use App\Inventory\Domain\InventoryItems;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineInventoryItems;
use App\Tests\Support\Trait\InventoryItemsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the {@see InventoryItemsContractCases} suite against the real
 * Doctrine + Postgres adapter. DAMA's PHPUnit extension wraps each
 * test in a transaction that is rolled back at teardown so rows do not
 * leak between tests.
 */
#[Medium]
final class DoctrineInventoryItemsContractTest extends KernelTestCase
{
    use InventoryItemsContractCases;

    private MockClock $mockClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    protected function inventoryItems(): InventoryItems
    {
        $items = static::getContainer()->get(InventoryItems::class);
        self::assertInstanceOf(DoctrineInventoryItems::class, $items);

        return $items;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
