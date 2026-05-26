<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-76: introduce the InventoryItem + StockBatch persistence schema and
 * the append-only inventory_stock_movements audit log.
 *
 * inventory_items: the InventoryItem aggregate root. Identity is a UUID
 * v7 minted by the IdentityGenerator port. listing_id is a UNIQUE
 * cross-context reference to a Catalog listing (no DB-level FK across
 * the context boundary — the foreign key is logical and enforced by the
 * RegisterInventoryItem application service in LRA-78).
 *
 * inventory_stock_batches: child entity of InventoryItem with per-facility
 * partitioning. The composite (item_id, facility_code, received_at, id)
 * index supports per-facility FIFO consumption via an index-only scan;
 * the (facility_code, item_id) index supports facility-scoped totals.
 *
 * The append-only inventory_stock_movements ledger is introduced by
 * LRA-83 along with its first writer (the Catalog→Inventory ACL) — the
 * column shape is finalised there so the kind/reason contract is
 * defined and exercised in a single change.
 */
final class Version20260526040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Inventory item/batch persistence schema (LRA-76).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (BOOLEAN, TIMESTAMP(0) WITHOUT TIME ZONE, CHECK).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_items (
                id CHAR(36) NOT NULL,
                listing_id CHAR(36) NOT NULL,
                primary_vendor_id CHAR(36) DEFAULT NULL,
                pos_color CHAR(7) NOT NULL,
                track_inventory BOOLEAN DEFAULT false NOT NULL,
                rentable BOOLEAN DEFAULT false NOT NULL,
                reorder_threshold INT DEFAULT NULL,
                archived BOOLEAN DEFAULT false NOT NULL,
                registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_inventory_items_listing_id ON inventory_items (listing_id)');
        $this->addSql('CREATE INDEX IDX_inventory_items_archived ON inventory_items (archived)');

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_stock_batches (
                id CHAR(36) NOT NULL,
                item_id CHAR(36) NOT NULL,
                facility_code VARCHAR(32) NOT NULL,
                received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                original_quantity INT NOT NULL,
                remaining_quantity INT NOT NULL,
                cost_per_unit_cents BIGINT NOT NULL,
                source_line_id CHAR(36) DEFAULT NULL,
                comments VARCHAR(1000) DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_inventory_stock_batches_item
                    FOREIGN KEY (item_id) REFERENCES inventory_items (id)
                    ON DELETE RESTRICT
            )
        SQL);
        $this->addSql(
            'CREATE INDEX IDX_inventory_stock_batches_fifo '
            . 'ON inventory_stock_batches (item_id, facility_code, received_at, id)',
        );
        $this->addSql(
            'CREATE INDEX IDX_inventory_stock_batches_facility '
            . 'ON inventory_stock_batches (facility_code, item_id)',
        );

    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS inventory_stock_batches');
        $this->addSql('DROP TABLE IF EXISTS inventory_items');
    }
}
