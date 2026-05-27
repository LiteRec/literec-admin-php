<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-94: introduce the inventory_stock_movements ledger.
 *
 * Append-only ledger written by:
 *  - {@see App\Inventory\Infrastructure\Acl\CatalogIntegrationListener}
 *    inline for SALE / RENTAL_CHECKOUT consume rows so the
 *    transaction_id idempotency key is captured in the same envelope
 *    handler that performs the consume.
 *  - Post-commit event subscribers under src/Inventory/Infrastructure/Acl/
 *    for every other movement (receive, return, transfer, adjustment).
 *
 * The UNIQUE PARTIAL index on (transaction_id, item_id, facility_code)
 * WHERE transaction_id IS NOT NULL is the idempotency key the ACL
 * relies on. The partial filter keeps non-consume rows (which carry
 * NULL transaction_id) out of the uniqueness constraint, so multiple
 * receives / adjustments per item per facility per day still insert
 * without conflict.
 */
final class Version20260528000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the inventory_stock_movements ledger (LRA-94).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (CHAR(36), TIMESTAMP(0) WITHOUT TIME ZONE, partial UNIQUE index).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_stock_movements (
                id CHAR(36) NOT NULL,
                item_id CHAR(36) NOT NULL,
                facility_code VARCHAR(32) NOT NULL,
                stock_batch_id CHAR(36) DEFAULT NULL,
                kind VARCHAR(24) NOT NULL,
                reason VARCHAR(32) NOT NULL,
                quantity INT NOT NULL,
                cost_per_unit_cents BIGINT NOT NULL,
                operator_note VARCHAR(1000) DEFAULT NULL,
                transaction_id VARCHAR(255) DEFAULT NULL,
                recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(
            'CREATE INDEX IDX_inventory_stock_movements_item_facility_at '
            . 'ON inventory_stock_movements (item_id, facility_code, recorded_at DESC)',
        );
        $this->addSql(
            'CREATE INDEX IDX_inventory_stock_movements_facility_at '
            . 'ON inventory_stock_movements (facility_code, recorded_at DESC)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_inventory_stock_movements_dedupe '
            . 'ON inventory_stock_movements (transaction_id, item_id, facility_code) '
            . 'WHERE transaction_id IS NOT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS inventory_stock_movements');
    }
}
