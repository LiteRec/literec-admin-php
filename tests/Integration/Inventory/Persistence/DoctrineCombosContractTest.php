<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Persistence;

use App\Inventory\Domain\Combos;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineCombos;
use App\Tests\Support\Trait\CombosContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

#[Medium]
final class DoctrineCombosContractTest extends KernelTestCase
{
    use CombosContractCases;

    private MockClock $mockClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    protected function combos(): Combos
    {
        return static::getContainer()->get(Combos::class);
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }

    #[Test]
    #[TestDox('Production binding for App\\Inventory\\Domain\\Combos resolves to DoctrineCombos.')]
    public function container_provides_doctrine_combos_implementation(): void
    {
        self::assertInstanceOf(DoctrineCombos::class, static::getContainer()->get(Combos::class));
    }
}
