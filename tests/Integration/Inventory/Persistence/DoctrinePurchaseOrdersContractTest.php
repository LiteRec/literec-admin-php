<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Persistence;

use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrinePurchaseOrders;
use App\Tests\Support\Trait\PurchaseOrdersContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

#[Medium]
final class DoctrinePurchaseOrdersContractTest extends KernelTestCase
{
    use PurchaseOrdersContractCases;

    private MockClock $mockClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
    }

    protected function purchaseOrders(): PurchaseOrders
    {
        $repo = static::getContainer()->get(PurchaseOrders::class);
        self::assertInstanceOf(DoctrinePurchaseOrders::class, $repo);

        return $repo;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
