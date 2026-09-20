<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Persistence;

use App\Administration\Domain\Roles;
use App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRoles;
use App\Tests\Support\Trait\RolesContractCases;
use DateTimeImmutable;
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

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mockClock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    protected function roles(): Roles
    {
        return static::getContainer()->get(Roles::class);
    }

    protected function clock(): MockClock
    {
        return $this->mockClock;
    }

    #[Test]
    #[TestDox('Production binding for App\\Administration\\Domain\\Roles resolves to DoctrineRoles.')]
    public function container_provides_doctrine_roles_implementation(): void
    {
        self::assertInstanceOf(DoctrineRoles::class, static::getContainer()->get(Roles::class));
    }
}
