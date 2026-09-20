<?php

declare(strict_types=1);

namespace App\Tests\Integration\Administration\Persistence;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\ConcurrentAdministratorModification;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Direct integration coverage for the Doctrine optimistic lock on
 * Administrator (LRA-269), mirroring {@see \App\Tests\Integration\Users\Persistence\DoctrineUsersOptimisticLockTest}.
 * The six lifecycle command handlers that would normally drive this
 * aggregate are LRA-279's scope, so this test constructs and mutates the
 * aggregate directly through the {@see Administrators} port instead of
 * dispatching a command.
 */
#[Medium]
#[Group('database')]
final class DoctrineAdministratorsOptimisticLockTest extends KernelTestCase
{
    private const string ADMINISTRATOR_ID = '019571bf-5d51-7000-b500-00000000b001';
    private const string SIGN_IN_ACCOUNT_ID = '019571bf-5d51-7000-b500-00000000b002';
    private const string RANK_ID = '019571bf-5d51-7000-b500-00000000b003';
    private const string OTHER_RANK_ID = '019571bf-5d51-7000-b500-00000000b004';
    private const string TENURE_ID = '019571bf-5d51-7000-b500-00000000b005';

    #[Test]
    #[TestDox('save() throws ConcurrentAdministratorModification when the row was modified since it was loaded.')]
    public function save_throws_when_the_row_was_modified_concurrently(): void
    {
        self::bootKernel();
        $administrators = $this->seedAdministrator();

        $administrator = $administrators->byId(AdministratorId::fromString(self::ADMINISTRATOR_ID));

        // Simulate a concurrent write having already committed a change
        // to this row between this process loading it and saving it.
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement(
            'UPDATE administration_administrators SET version = version + 1 WHERE id = ?',
            [$administrator->id()->value],
        );

        $administrator->changeRankTo(
            RankId::fromString(self::OTHER_RANK_ID),
            Actor::system(),
            new MockClock(new DateTimeImmutable('2026-01-01 12:00:00')),
        );

        $this->expectException(ConcurrentAdministratorModification::class);

        $administrators->save($administrator);
    }

    #[Test]
    #[TestDox('save() increments version by one on an uncontested change.')]
    public function save_increments_version_on_the_happy_path(): void
    {
        self::bootKernel();
        $administrators = $this->seedAdministrator();

        $administrator = $administrators->byId(AdministratorId::fromString(self::ADMINISTRATOR_ID));
        $versionBeforeSave = $administrator->version();

        $administrator->changeRankTo(
            RankId::fromString(self::OTHER_RANK_ID),
            Actor::system(),
            new MockClock(new DateTimeImmutable('2026-01-01 12:00:00')),
        );
        $administrators->save($administrator);

        self::assertSame($versionBeforeSave + 1, $administrator->version());
    }

    private function seedAdministrator(): Administrators
    {
        $administrators = static::getContainer()->get(Administrators::class);
        self::assertInstanceOf(Administrators::class, $administrators);

        $administrator = Administrator::grant(
            AdministratorId::fromString(self::ADMINISTRATOR_ID),
            SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_ID),
            RankId::fromString(self::RANK_ID),
            AdministratorTenureId::fromString(self::TENURE_ID),
            Actor::system(),
            new MockClock(new DateTimeImmutable('2026-01-01 12:00:00')),
        );
        $administrator->releaseEvents();
        $administrators->add($administrator);

        return $administrators;
    }
}
