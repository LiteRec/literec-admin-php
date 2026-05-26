<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Persistence;

use App\Inventory\Domain\ItemLinks;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineItemLinks;
use App\Tests\Support\Trait\ItemLinksContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

#[Medium]
final class DoctrineItemLinksContractTest extends KernelTestCase
{
    use ItemLinksContractCases;

    private MockClock $mockClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-26 12:00:00'));
    }

    protected function itemLinks(): ItemLinks
    {
        $repo = static::getContainer()->get(ItemLinks::class);
        self::assertInstanceOf(DoctrineItemLinks::class, $repo);

        return $repo;
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
