<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use RuntimeException;

/**
 * Fast snapshot/restore for a Postgres database using template databases
 * (`CREATE DATABASE ... TEMPLATE ...`), which clones a database server-side in
 * seconds without re-running the slow command-dispatch fixtures (LRA-177).
 *
 * Both operations run through a maintenance connection to the `postgres`
 * database because a database cannot be dropped or used as a clone template
 * while connections (including our own) are open to it. The application
 * connection is closed first and any remaining backends are terminated.
 *
 * Scoped to the E2E lane by the calling commands via {@see E2eDatabaseGuard};
 * this class itself is generic and acts on whatever database the injected
 * connection targets.
 */
final class PostgresSnapshot
{
    private const SNAPSHOT_SUFFIX = '_snapshot';
    private const MAINTENANCE_DATABASE = 'postgres';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Derives the snapshot database name for a given database. Pure so the
     * naming contract can be unit-tested without a live connection.
     */
    public static function snapshotNameFor(string $databaseName): string
    {
        return $databaseName . self::SNAPSHOT_SUFFIX;
    }

    public function snapshotName(): string
    {
        return self::snapshotNameFor($this->databaseName());
    }

    /**
     * Captures the current database into a fresh snapshot database, replacing
     * any previous snapshot of the same name.
     *
     * Side effect: closes the injected connection (a TEMPLATE source cannot
     * have open connections) and does not reopen it. Create a fresh
     * {@see PostgresSnapshot} per operation rather than reusing this instance.
     */
    public function capture(): void
    {
        $source = $this->databaseName();
        $snapshot = $this->snapshotName();

        // A template source cannot have open connections; release ours first.
        $this->connection->close();

        $maintenance = $this->maintenanceConnection();

        try {
            $this->terminateConnections($maintenance, $source);
            $maintenance->executeStatement(sprintf('DROP DATABASE IF EXISTS %s', $this->quoteIdentifier($snapshot)));
            $maintenance->executeStatement(sprintf(
                'CREATE DATABASE %s TEMPLATE %s',
                $this->quoteIdentifier($snapshot),
                $this->quoteIdentifier($source),
            ));
        } finally {
            $maintenance->close();
        }
    }

    /**
     * Restores the database from its snapshot, dropping and recreating it from
     * the template. Throws when no snapshot exists yet.
     *
     * Side effect: closes the injected connection (the target is dropped) and
     * does not reopen it. Create a fresh {@see PostgresSnapshot} per operation
     * rather than reusing this instance.
     */
    public function restore(): void
    {
        $target = $this->databaseName();
        $snapshot = $this->snapshotName();

        $this->connection->close();

        $maintenance = $this->maintenanceConnection();

        try {
            $this->guardSnapshotExists($maintenance, $snapshot);
            $this->terminateConnections($maintenance, $target);
            $maintenance->executeStatement(sprintf('DROP DATABASE IF EXISTS %s', $this->quoteIdentifier($target)));
            $maintenance->executeStatement(sprintf(
                'CREATE DATABASE %s TEMPLATE %s',
                $this->quoteIdentifier($target),
                $this->quoteIdentifier($snapshot),
            ));
        } finally {
            $maintenance->close();
        }
    }

    private function databaseName(): string
    {
        $name = $this->connection->getDatabase();

        if ($name === null) {
            throw new RuntimeException('No database name could be resolved from the connection.');
        }

        return $name;
    }

    /**
     * Opens a connection to the `postgres` maintenance database on the same
     * server, reusing every parameter of the application connection (host,
     * port, credentials, TLS/socket and driver options) and only swapping the
     * target database name. The transient `url`, the `dbname_suffix` (which DBAL
     * would otherwise append to the maintenance database name, e.g. the test
     * env's `_test`) and the DAMA test-bundle marker are dropped so they cannot
     * interfere with the maintenance connection.
     */
    private function maintenanceConnection(): Connection
    {
        $params = $this->connection->getParams();
        $params['dbname'] = self::MAINTENANCE_DATABASE;
        unset($params['url'], $params['dbname_suffix'], $params['dama.connection_key']);

        return DriverManager::getConnection($params);
    }

    private function terminateConnections(Connection $maintenance, string $database): void
    {
        $maintenance->executeStatement(
            'SELECT pg_terminate_backend(pid) FROM pg_stat_activity '
            . 'WHERE datname = ? AND pid <> pg_backend_pid()',
            [$database],
        );
    }

    private function guardSnapshotExists(Connection $maintenance, string $snapshot): void
    {
        $exists = $maintenance->fetchOne('SELECT 1 FROM pg_database WHERE datname = ?', [$snapshot]);

        if ($exists === false) {
            throw new RuntimeException(sprintf(
                'Snapshot database "%s" does not exist. Run app:seed:e2e to capture one first.',
                $snapshot,
            ));
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
