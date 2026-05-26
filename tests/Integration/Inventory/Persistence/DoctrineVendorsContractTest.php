<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Persistence;

use App\Inventory\Domain\Vendors;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineVendors;
use App\Tests\Support\Trait\VendorsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the {@see VendorsContractCases} suite against the real
 * Doctrine + Postgres adapter. DAMA's PHPUnit extension wraps each
 * test in a transaction that is rolled back at teardown so vendor
 * rows do not leak between tests.
 */
#[Medium]
final class DoctrineVendorsContractTest extends KernelTestCase
{
    use VendorsContractCases;

    private MockClock $mockClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    protected function vendors(): Vendors
    {
        $vendors = static::getContainer()->get(Vendors::class);
        // Lock the integration test to the Doctrine adapter — if the
        // production binding ever switches to a different concrete
        // implementation, this suite should fail loudly so a separate
        // contract test is added for the new adapter.
        self::assertInstanceOf(DoctrineVendors::class, $vendors);

        return $vendors;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
