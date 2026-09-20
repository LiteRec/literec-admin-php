<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Persistence;

use App\Administration\Domain\Roles;
use App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRoles;
use App\Tests\Support\Trait\RolesContractCases;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

#[Medium]
final class DoctrineRolesContractTest extends KernelTestCase
{
    use RolesContractCases;

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

    protected function roles(): Roles
    {
        return static::getContainer()->get(Roles::class);
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }

    /**
     * Clears the EntityManager's identity map so the next byId()/byName()
     * call issues a genuine query and hydrates a fresh instance from the
     * database, rather than Doctrine handing back the exact same
     * in-memory object a prior persist() in this test already holds —
     * see {@see RolesContractCases::resetPersistenceContext()} docblock.
     */
    protected function resetPersistenceContext(): void
    {
        $this->em->clear();
    }

    #[Test]
    #[TestDox('Production binding for App\\Administration\\Domain\\Roles resolves to DoctrineRoles.')]
    public function container_provides_doctrine_roles_implementation(): void
    {
        self::assertInstanceOf(DoctrineRoles::class, static::getContainer()->get(Roles::class));
    }
}
