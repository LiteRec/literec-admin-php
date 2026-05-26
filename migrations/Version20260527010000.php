<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-93: introduce the ItemLink persistence schema.
 *
 * First slice of the consolidated LRA-93 Doctrine persistence work —
 * swaps the in-memory fail-loud production binding for
 * App\Inventory\Domain\ItemLinks over to a Postgres-backed adapter.
 *
 * inventory_item_links: parent-child availability rule entity introduced
 * by LRA-82. Fields mirror the in-memory state precisely so the
 * existing aggregate, application services, and ACL pre-check
 * (App\Inventory\Domain\ItemLink::wouldViolateAt()) continue working
 * unchanged.
 *
 * Indexes:
 *  - UNIQUE(master_item_id, linked_item_id) enforces the no-duplicate-
 *    pair invariant at the database level (the in-memory adapter
 *    already enforced the same rule via existsForPair).
 *  - (master_item_id) backs the activeForMaster() read path the ACL
 *    walks before consuming stock.
 */
final class Version20260527010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the inventory_item_links table (LRA-93 / closes LRA-82 deferral).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (CHAR(36), TIMESTAMP(0) WITHOUT TIME ZONE, BOOLEAN).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_item_links (
                id CHAR(36) NOT NULL,
                master_item_id CHAR(36) NOT NULL,
                linked_item_id CHAR(36) NOT NULL,
                reserved_quantity INT NOT NULL,
                unlimited BOOLEAN DEFAULT false NOT NULL,
                min_required INT NOT NULL,
                max_per_purchase INT NOT NULL,
                include_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_inventory_item_links_pair '
            . 'ON inventory_item_links (master_item_id, linked_item_id)',
        );
        $this->addSql('CREATE INDEX IDX_inventory_item_links_master ON inventory_item_links (master_item_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS inventory_item_links');
    }
}
