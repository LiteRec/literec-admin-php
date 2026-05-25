<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Persistence;

use App\Inventory\Domain\Vendors;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryVendors;
use App\Tests\Support\Trait\VendorsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the {@see VendorsContractCases} suite against the in-memory
 * adapter so the Domain layer can exercise the port without booting
 * Symfony or hitting Postgres.
 */
#[Small]
final class InMemoryVendorsContractTest extends TestCase
{
    use VendorsContractCases;

    private InMemoryVendors $repository;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repository = new InMemoryVendors();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    protected function vendors(): Vendors
    {
        return $this->repository;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
