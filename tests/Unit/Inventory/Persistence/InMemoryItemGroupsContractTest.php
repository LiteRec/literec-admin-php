<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Persistence;

use App\Inventory\Domain\ItemGroups;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryItemGroups;
use App\Tests\Support\Trait\ItemGroupsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryItemGroupsContractTest extends TestCase
{
    use ItemGroupsContractCases;

    private InMemoryItemGroups $repo;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryItemGroups();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    protected function itemGroups(): ItemGroups
    {
        return $this->repo;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
