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
 * The Doctrine repository adapters (DoctrineInventoryItems,
 * DoctrinePurchaseOrders) translate the raw
 * Doctrine\ORM\OptimisticLockException into named domain exceptions
 * (ConcurrentInventoryItemModification,
 * ConcurrentPurchaseOrderModification) so controllers can map the
 * race to HTTP 409 without importing Doctrine types.
 */
final class Version20260528030000 extends AbstractMigration
{
    /** @var list<string> Tables receiving the version column. */
    private const array TABLES = [
        'inventory_items',
        'inventory_purchase_orders',
    ];

    public function getDescription(): string
    {
        return 'Add optimistic-lock version columns to inventory_items + inventory_purchase_orders (LRA-99).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-99 requires PostgreSQL.',
        );

        foreach (self::TABLES as $table) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD COLUMN version INTEGER NOT NULL DEFAULT 0',
                $table,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-99 requires PostgreSQL.',
        );

        foreach (array_reverse(self::TABLES) as $table) {
            $this->addSql(sprintf(
                'ALTER TABLE %s DROP COLUMN IF EXISTS version',
                $table,
            ));
        }
    }
}
