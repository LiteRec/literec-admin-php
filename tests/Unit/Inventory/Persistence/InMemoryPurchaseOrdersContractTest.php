<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Persistence;

use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryPurchaseOrders;
use App\Tests\Support\Trait\PurchaseOrdersContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryPurchaseOrdersContractTest extends TestCase
{
    use PurchaseOrdersContractCases;

    private InMemoryPurchaseOrders $repository;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPurchaseOrders();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
    }

    protected function purchaseOrders(): PurchaseOrders
    {
        return $this->repository;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
