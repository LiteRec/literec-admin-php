<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Persistence;

use App\Inventory\Domain\Combos;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryCombos;
use App\Tests\Support\Trait\CombosContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class InMemoryCombosContractTest extends TestCase
{
    use CombosContractCases;

    private InMemoryCombos $repo;
    private MockClock $mockClock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryCombos();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    protected function combos(): Combos
    {
        return $this->repo;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
