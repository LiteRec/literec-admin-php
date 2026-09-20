<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Persistence;

use App\Administration\Domain\Administrators;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryAdministrators;
use App\Tests\Support\Trait\AdministratorsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryAdministratorsContractTest extends TestCase
{
    use AdministratorsContractCases;

    private InMemoryAdministrators $repo;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryAdministrators();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    protected function administrators(): Administrators
    {
        return $this->repo;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }

    protected function resetPersistenceContext(): void
    {
        // No-op: InMemoryAdministrators is a plain array store with no
        // identity map to reset — reading it back already is the round
        // trip.
    }
}
