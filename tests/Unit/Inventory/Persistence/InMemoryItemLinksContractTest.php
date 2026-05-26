<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Persistence;

use App\Inventory\Domain\ItemLinks;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryItemLinks;
use App\Tests\Support\Trait\ItemLinksContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryItemLinksContractTest extends TestCase
{
    use ItemLinksContractCases;

    private InMemoryItemLinks $repository;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repository = new InMemoryItemLinks();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-26 12:00:00'));
    }

    protected function itemLinks(): ItemLinks
    {
        return $this->repository;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
