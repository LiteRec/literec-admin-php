<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Infrastructure\Persistence\InMemory;

use App\Households\Domain\Households;
use App\Households\Domain\MemberCodeAllocator;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryMemberCodeAllocator;
use App\Tests\Support\Trait\HouseholdsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryHouseholdsContractTest extends TestCase
{
    use HouseholdsContractCases;

    private InMemoryHouseholds $repo;
    private InMemoryMemberCodeAllocator $allocator;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryHouseholds();
        $this->allocator = new InMemoryMemberCodeAllocator();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    protected function households(): Households
    {
        return $this->repo;
    }

    protected function memberCodeAllocator(): MemberCodeAllocator
    {
        return $this->allocator;
    }

    protected function clock(): MockClock
    {
        return $this->clock;
    }
}
