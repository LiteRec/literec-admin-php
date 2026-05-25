<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Persistence;

use App\Catalog\Domain\Listings;
use App\Tests\Support\Trait\ListingsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the {@see ListingsContractCases} suite against the real
 * Doctrine + Postgres adapter. DAMA's PHPUnit extension wraps each
 * test in a transaction that is rolled back at teardown so listing
 * rows do not leak between tests.
 */
#[Medium]
final class DoctrineListingsContractTest extends KernelTestCase
{
    use ListingsContractCases;

    private MockClock $mockClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    protected function listings(): Listings
    {
        $listings = static::getContainer()->get(Listings::class);
        self::assertInstanceOf(Listings::class, $listings);

        return $listings;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
