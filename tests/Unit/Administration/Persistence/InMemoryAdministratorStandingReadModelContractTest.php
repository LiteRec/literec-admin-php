<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Persistence;

use App\Administration\Application\Query\Port\AdministratorStandingReadModel;
use App\Administration\Domain\Administrators;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryAdministrators;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryAdministratorStandingReadModel;
use App\Tests\Support\Trait\AdministratorStandingContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryAdministratorStandingReadModelContractTest extends TestCase
{
    use AdministratorStandingContractCases;

    private InMemoryAdministrators $administrators;
    private InMemoryAdministratorStandingReadModel $readModel;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->administrators = new InMemoryAdministrators();
        $this->readModel = new InMemoryAdministratorStandingReadModel($this->administrators);
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    protected function readModel(): AdministratorStandingReadModel
    {
        return $this->readModel;
    }

    protected function administrators(): Administrators
    {
        return $this->administrators;
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
