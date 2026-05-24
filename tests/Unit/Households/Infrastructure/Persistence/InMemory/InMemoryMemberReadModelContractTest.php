<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Infrastructure\Persistence\InMemory;

use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryMemberReadModel;
use App\Tests\Support\Trait\MemberReadModelContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryMemberReadModelContractTest extends TestCase
{
    use MemberReadModelContractCases;

    private InMemoryMemberReadModel $readModel;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->readModel = new InMemoryMemberReadModel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    protected function readModel(): MemberReadModel
    {
        return $this->readModel;
    }

    protected function seedHouseholds(array $households): void
    {
        foreach ($households as $household) {
            $this->readModel->withHousehold($household);
        }
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
