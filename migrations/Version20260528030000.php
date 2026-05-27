<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-99: add Doctrine optimistic-lock version columns to
 * inventory_items + inventory_purchase_orders.
 *
 * The application-side WrapsOptimisticLock trait translates the
 * raw Doctrine\ORM\OptimisticLockException into named domain
 * exceptions (ConcurrentInventoryItemModification,
 * ConcurrentPurchaseOrderModification) so controllers can map the
 * race to HTTP 409 without importing Doctrine types.
 */
final class Version20260528030000 extends AbstractMigration
{
    /**
     * @return list<string>
     */
    private static function forwardStatements(): array
    {
        return [
            'ALTER TABLE inventory_items '
                . 'ADD COLUMN version INTEGER NOT NULL DEFAULT 0',
            'ALTER TABLE inventory_purchase_orders '
                . 'ADD COLUMN version INTEGER NOT NULL DEFAULT 0',
        ];
    }

    /**
     * @return list<string>
     */
    private static function reverseStatements(): array
    {
        return [
            'ALTER TABLE inventory_items DROP COLUMN IF EXISTS version',
            'ALTER TABLE inventory_purchase_orders DROP COLUMN IF EXISTS version',
        ];
    }

    public function getDescription(): string
    {
        return 'Add optimistic-lock version columns to inventory_items + inventory_purchase_orders (LRA-99).';
    }

    public function up(Schema $schema): void
    {
        $this->guardPostgres();
        foreach (self::forwardStatements() as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->guardPostgres();
        foreach (self::reverseStatements() as $sql) {
            $this->addSql($sql);
        }
    }

    private function guardPostgres(): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-99 migration requires PostgreSQL.',
        );
    }
}
