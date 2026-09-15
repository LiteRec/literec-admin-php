<?php

declare(strict_types=1);

namespace App\Tests\Integration\Users\Persistence;

use App\Users\Application\Command\RegisterUser;
use App\Users\Domain\Exception\ConcurrentUserModification;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\Username;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Direct integration coverage for the Doctrine optimistic lock on User
 * (LRA-213 review round). A stale `version` — the row having been modified
 * since this process loaded it, exactly what happens when two logins race
 * to consume the same one-time password — must make save() throw
 * ConcurrentUserModification rather than silently overwrite the concurrent
 * change. {@see \App\Tests\Unit\Users\Infrastructure\Security\ConsumeOneTimePasswordOnLoginTest}
 * covers the listener's reaction to that failure in isolation; this test
 * covers the mapping + translation that actually produces it.
 */
#[Medium]
#[Group('database')]
final class DoctrineUsersOptimisticLockTest extends KernelTestCase
{
    private const string SAMPLE_HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR';

    #[Test]
    #[TestDox('save() throws ConcurrentUserModification when the row was modified since it was loaded.')]
    public function save_throws_when_the_row_was_modified_concurrently(): void
    {
        self::bootKernel();
        $this->registerUser('otp_lock_e2e');

        $users = static::getContainer()->get(Users::class);
        self::assertInstanceOf(Users::class, $users);
        $user = $users->byUsername(Username::of('otp_lock_e2e'));

        // Simulate a concurrent login having already committed a change to
        // this row between this process loading it and saving it.
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement(
            'UPDATE "user" SET version = version + 1 WHERE id = ?',
            [$user->id()->value],
        );

        $user->issueOneTimePassword(
            HashedPassword::fromHash(self::SAMPLE_HASH),
            new MockClock(new DateTimeImmutable('2026-01-01 12:00:00')),
        );

        $this->expectException(ConcurrentUserModification::class);

        $users->save($user);
    }

    #[Test]
    #[TestDox('save() increments version by one on an uncontested change.')]
    public function save_increments_version_on_the_happy_path(): void
    {
        self::bootKernel();
        $this->registerUser('otp_lock_happy_e2e');

        $users = static::getContainer()->get(Users::class);
        self::assertInstanceOf(Users::class, $users);
        $user = $users->byUsername(Username::of('otp_lock_happy_e2e'));
        $versionBeforeSave = $user->version();

        $user->issueOneTimePassword(
            HashedPassword::fromHash(self::SAMPLE_HASH),
            new MockClock(new DateTimeImmutable('2026-01-01 12:00:00')),
        );
        $users->save($user);

        self::assertSame($versionBeforeSave + 1, $user->version());
    }

    private function registerUser(string $username): void
    {
        $bus = static::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new RegisterUser($username, 'CorrectHorseBattery!')); // NOSONAR test fixture
    }
}
