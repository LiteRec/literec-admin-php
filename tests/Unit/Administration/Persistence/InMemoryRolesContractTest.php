<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Persistence;

use App\Administration\Domain\Roles;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryRoles;
use App\Tests\Support\Trait\RolesContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryRolesContractTest extends TestCase
{
    use RolesContractCases;

    private InMemoryRoles $repo;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryRoles();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    protected function roles(): Roles
    {
        return $this->repo;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
