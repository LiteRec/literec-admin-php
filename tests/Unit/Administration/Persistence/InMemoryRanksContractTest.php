<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Persistence;

use App\Administration\Domain\Ranks;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryRanks;
use App\Tests\Support\Trait\RanksContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryRanksContractTest extends TestCase
{
    use RanksContractCases;

    private InMemoryRanks $repo;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryRanks();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    protected function ranks(): Ranks
    {
        return $this->repo;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }

    protected function resetPersistenceContext(): void
    {
        // No-op: InMemoryRanks is a plain array store with no identity
        // map to reset — reading it back already is the round trip.
    }
}
