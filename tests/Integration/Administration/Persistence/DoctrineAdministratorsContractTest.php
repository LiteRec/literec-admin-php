<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Persistence;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use App\Administration\Infrastructure\Persistence\Doctrine\DoctrineAdministrators;
use App\Tests\Support\Trait\AdministratorsContractCases;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

#[Medium]
final class DoctrineAdministratorsContractTest extends KernelTestCase
{
    use AdministratorsContractCases;

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

    protected function administrators(): Administrators
    {
        return static::getContainer()->get(Administrators::class);
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }

    /**
     * Clears the EntityManager's identity map so the next byId()/
     * forSignInAccount() call issues a genuine query and hydrates a
     * fresh instance from the database, rather than Doctrine handing
     * back the exact same in-memory object a prior persist() in this
     * test already holds — see {@see AdministratorsContractCases::resetPersistenceContext()}
     * docblock.
     */
    protected function resetPersistenceContext(): void
    {
        $this->em->clear();
    }

    #[Test]
    #[TestDox('Production binding for App\\Administration\\Domain\\Administrators resolves to DoctrineAdministrators.')]
    public function container_provides_doctrine_administrators_implementation(): void
    {
        self::assertInstanceOf(DoctrineAdministrators::class, static::getContainer()->get(Administrators::class));
    }

    /**
     * Doctrine-specific: the in-memory adapter has no real unique
     * constraints on the join table, so this cannot be part of the
     * shared contract. Reproduces the exact race the review finding
     * described — a row already present in administration_administrator_roles
     * that this process's in-memory aggregate does not know about —
     * by inserting it directly, then flushing an assignRole() for the
     * same (administrator, role) pair through the aggregate. Before the
     * fix, this surfaced as SignInAccountAlreadyAnAdministrator.
     */
    #[Test]
    #[TestDox('save() rethrows an unrelated unique violation instead of SignInAccountAlreadyAnAdministrator.')]
    public function save_rethrows_unrelated_unique_violation(): void
    {
        $administratorId = AdministratorId::fromString('019571bf-5d51-7000-b500-00000000b101');
        $roleId = RoleId::fromString('019571bf-5d51-7000-b500-00000000b102');

        $administrators = $this->administrators();
        $administrator = Administrator::grant(
            $administratorId,
            SignInAccountId::fromString('019571bf-5d51-7000-b500-00000000b103'),
            RankId::fromString('019571bf-5d51-7000-b500-00000000b104'),
            AdministratorTenureId::fromString('019571bf-5d51-7000-b500-00000000b105'),
            Actor::system(),
            $this->mockClock,
        );
        $administrator->releaseEvents();
        $administrators->add($administrator);

        // Simulate the row already existing out of band — a concurrent
        // assignRole() that committed first, or a direct data fix —
        // which this process's in-memory aggregate has no way to know
        // about.
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement(
            'INSERT INTO administration_administrator_roles (administrator_id, role_id, assigned_at) '
            . 'VALUES (:administratorId, :roleId, :assignedAt)',
            [
                'administratorId' => $administratorId->value,
                'roleId' => $roleId->value,
                'assignedAt' => $this->mockClock->now()->format('Y-m-d H:i:s'),
            ],
        );

        $administrator->assignRole($roleId, Actor::system(), $this->mockClock);

        // instanceof, not exact-class matching: PHPUnit's expectException()
        // would also accept the very SignInAccountAlreadyAnAdministrator
        // mis-report this test exists to catch, since PHP exceptions are
        // matched by instanceof and neither type extends the other — so a
        // regression back to the blanket catch fails this assertion
        // outright rather than passing it by accident.
        $this->expectException(UniqueConstraintViolationException::class);
        $this->expectExceptionMessageMatches('/administration_administrator_roles_pkey/');

        $administrators->save($administrator);
    }
}
