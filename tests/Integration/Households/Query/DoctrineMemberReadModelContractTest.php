<?php

declare(strict_types=1);

namespace App\Tests\Integration\Households\Query;

use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Domain\Households;
use App\Tests\Support\Trait\MemberReadModelContractCases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Medium;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the {@see MemberReadModelContractCases} suite against the real
 * Doctrine DBAL adapter. Seeds Households through the write-side
 * {@see Households} repository (so the same migration-defined schema
 * receives the data the read model then queries via raw SQL).
 *
 * DAMA's PHPUnit extension wraps each test in a transaction rolled back
 * at teardown so rows do not leak between tests.
 */
#[Medium]
final class DoctrineMemberReadModelContractTest extends KernelTestCase
{
    use MemberReadModelContractCases;

    private MockClock $mockClock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    protected function readModel(): MemberReadModel
    {
        $readModel = static::getContainer()->get(MemberReadModel::class);
        self::assertInstanceOf(MemberReadModel::class, $readModel);

        return $readModel;
    }

    protected function seedHouseholds(array $households): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        foreach ($households as $household) {
            $repo->save($household);
        }
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }
}
