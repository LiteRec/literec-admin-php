<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Persistence;

use App\Catalog\Domain\Listings;
use App\Catalog\Infrastructure\Persistence\InMemory\InMemoryListings;
use App\Tests\Support\Trait\ListingsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the {@see ListingsContractCases} suite against the in-memory
 * adapter so the Domain layer can exercise the port without booting
 * Symfony or hitting Postgres.
 */
#[Small]
final class InMemoryListingsContractTest extends TestCase
{
    use ListingsContractCases;

    private InMemoryListings $repository;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repository = new InMemoryListings();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    protected function listings(): Listings
    {
        return $this->repository;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
