<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Database;

use App\Shared\Infrastructure\Database\PostgresSnapshot;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Exercises the real template-database capture/restore against a throwaway
 * scratch database (never app_test), using DriverManager connections so the
 * DDL runs outside DAMA's per-test transaction wrapper. Slow group so it runs
 * in the dedicated fixtures CI job, not the fast unit/functional tiers.
 */
#[Medium]
#[Group('slow')]
final class PostgresSnapshotIntegrationTest extends KernelTestCase
{
    private const SCRATCH_DATABASE = 'app_pgsnap_selftest';

    /** @var array<string, mixed> */
    private array $baseParams;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        $this->baseParams = $connection->getParams();
        $this->dropScratchDatabases();
        $this->createScratchDatabase();
    }

    protected function tearDown(): void
    {
        $this->dropScratchDatabases();
        parent::tearDown();
    }

    #[Test]
    #[TestDox('capture() then restore() rolls a mutated database back to the captured state.')]
    public function capture_then_restore_round_trips_the_database(): void
    {
        $seed = $this->connectionTo(self::SCRATCH_DATABASE);
        $seed->executeStatement('CREATE TABLE marker (id INT PRIMARY KEY)');
        $seed->executeStatement('INSERT INTO marker (id) VALUES (1)');
        $seed->close();

        (new PostgresSnapshot($this->connectionTo(self::SCRATCH_DATABASE)))->capture();

        $mutated = $this->connectionTo(self::SCRATCH_DATABASE);
        $mutated->executeStatement('INSERT INTO marker (id) VALUES (2)');
        self::assertSame(2, $this->countMarkers($mutated));
        $mutated->close();

        (new PostgresSnapshot($this->connectionTo(self::SCRATCH_DATABASE)))->restore();

        $restored = $this->connectionTo(self::SCRATCH_DATABASE);
        self::assertSame(1, $this->countMarkers($restored));
        $restored->close();
    }

    private function countMarkers(Connection $connection): int
    {
        $count = $connection->fetchOne('SELECT COUNT(*) FROM marker');

        return is_numeric($count) ? (int) $count : -1;
    }

    private function connectionTo(string $databaseName): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $this->baseStringParam('host'),
            'port' => $this->baseStringParam('port') === '' ? 5432 : (int) $this->baseStringParam('port'),
            'user' => $this->baseStringParam('user'),
            'password' => $this->baseStringParam('password'),
            'dbname' => $databaseName,
        ]);
    }

    private function baseStringParam(string $key): string
    {
        $value = $this->baseParams[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    private function createScratchDatabase(): void
    {
        $maintenance = $this->connectionTo('postgres');

        try {
            $maintenance->executeStatement(sprintf('CREATE DATABASE "%s"', self::SCRATCH_DATABASE));
        } finally {
            $maintenance->close();
        }
    }

    private function dropScratchDatabases(): void
    {
        $maintenance = $this->connectionTo('postgres');

        try {
            foreach ([self::SCRATCH_DATABASE, PostgresSnapshot::snapshotNameFor(self::SCRATCH_DATABASE)] as $database) {
                $maintenance->executeStatement(
                    'SELECT pg_terminate_backend(pid) FROM pg_stat_activity '
                    . 'WHERE datname = ? AND pid <> pg_backend_pid()',
                    [$database],
                );
                $maintenance->executeStatement(sprintf('DROP DATABASE IF EXISTS "%s"', $database));
            }
        } finally {
            $maintenance->close();
        }
    }
}
