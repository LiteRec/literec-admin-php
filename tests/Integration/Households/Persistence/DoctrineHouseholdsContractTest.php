<?php

declare(strict_types=1);

namespace App\Tests\Integration\Households\Persistence;

use App\Households\Domain\Households;
use App\Households\Domain\MemberCodeAllocator;
use App\Tests\Support\Trait\HouseholdsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the {@see HouseholdsContractCases} suite against the real
 * Doctrine + Postgres adapters. DAMA's PHPUnit extension wraps each
 * test in a transaction that is rolled back at teardown so the
 * household/member rows do not leak between tests.
 */
#[Medium]
final class DoctrineHouseholdsContractTest extends KernelTestCase
{
    use HouseholdsContractCases;

    private MockClock $mockClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    protected function households(): Households
    {
        $households = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $households);

        return $households;
    }

    protected function memberCodeAllocator(): MemberCodeAllocator
    {
        $allocator = static::getContainer()->get(MemberCodeAllocator::class);
        self::assertInstanceOf(MemberCodeAllocator::class, $allocator);

        return $allocator;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
