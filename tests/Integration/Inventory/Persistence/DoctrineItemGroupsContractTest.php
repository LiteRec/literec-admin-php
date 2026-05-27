<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Persistence;

use App\Inventory\Domain\ItemGroups;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineItemGroups;
use App\Tests\Support\Trait\ItemGroupsContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

#[Medium]
final class DoctrineItemGroupsContractTest extends KernelTestCase
{
    use ItemGroupsContractCases;

    private MockClock $mockClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    protected function itemGroups(): ItemGroups
    {
        return static::getContainer()->get(ItemGroups::class);
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }

    #[Test]
    #[TestDox('Production binding for App\\Inventory\\Domain\\ItemGroups resolves to DoctrineItemGroups.')]
    public function container_provides_doctrine_item_groups_implementation(): void
    {
        self::assertInstanceOf(DoctrineItemGroups::class, static::getContainer()->get(ItemGroups::class));
    }
}
