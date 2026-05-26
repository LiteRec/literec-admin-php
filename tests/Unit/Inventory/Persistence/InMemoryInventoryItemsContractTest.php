<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Persistence;

use App\Inventory\Domain\InventoryItems;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use App\Tests\Support\Trait\InventoryItemsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the {@see InventoryItemsContractCases} suite against the
 * in-memory adapter so the Domain layer can exercise the port without
 * booting Symfony or hitting Postgres.
 */
#[Small]
final class InMemoryInventoryItemsContractTest extends TestCase
{
    use InventoryItemsContractCases;

    private InMemoryInventoryItems $repository;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repository = new InMemoryInventoryItems();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    protected function inventoryItems(): InventoryItems
    {
        return $this->repository;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
