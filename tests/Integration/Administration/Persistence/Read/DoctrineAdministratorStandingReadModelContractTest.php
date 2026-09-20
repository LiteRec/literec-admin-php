<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Persistence\Read;

use App\Administration\Application\Query\Port\AdministratorStandingReadModel;
use App\Administration\Domain\Administrators;
use App\Administration\Infrastructure\Persistence\Doctrine\Read\DoctrineAdministratorStandingReadModel;
use App\Tests\Support\Trait\AdministratorStandingContractCases;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the {@see AdministratorStandingContractCases} suite against the
 * real Doctrine DBAL adapter. Seeds through the write-side
 * {@see Administrators} repository so the same migration-defined tables
 * receive the data the read model then queries via raw SQL — CQRS-lite,
 * same pattern as DoctrineRoleReadModelTest / DoctrineMemberReadModelContractTest.
 *
 * DAMA's PHPUnit extension wraps each test in a transaction rolled back
 * at teardown so rows do not leak between tests.
 */
#[Medium]
final class DoctrineAdministratorStandingReadModelContractTest extends KernelTestCase
{
    use AdministratorStandingContractCases;

    private MockClock $mockClock;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
    }

    protected function readModel(): AdministratorStandingReadModel
    {
        $readModel = static::getContainer()->get(AdministratorStandingReadModel::class);
        self::assertInstanceOf(AdministratorStandingReadModel::class, $readModel);

        return $readModel;
    }

    protected function administrators(): Administrators
    {
        return static::getContainer()->get(Administrators::class);
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }

    protected function resetPersistenceContext(): void
    {
        $this->em->clear();
    }

    #[Test]
    #[TestDox('Production binding for AdministratorStandingReadModel resolves to the Doctrine adapter.')]
    public function container_provides_doctrine_administrator_standing_read_model_implementation(): void
    {
        self::assertInstanceOf(
            DoctrineAdministratorStandingReadModel::class,
            static::getContainer()->get(AdministratorStandingReadModel::class),
        );
    }
}
