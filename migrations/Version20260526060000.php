<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-77: PurchaseOrder + PurchaseOrderLine persistence schema.
 *
 * inventory_purchase_orders: the aggregate root. vendor_id and
 * facility_code are scalar VO columns; no FK across aggregate
 * boundaries. status is the PurchaseOrderStatus enum value.
 *
 * inventory_purchase_order_lines: child entity owned by the PO, with
 * orphan-removal cascade. ON DELETE CASCADE matches the orphan-removal
 * mapping so any PO deletion cleans up its lines.
 *
 * Indexes follow the named-finder query shapes on the PurchaseOrders
 * port: (vendor_id, status) for openByVendor, (facility_code, status)
 * for byFacility, (status, created_at) for byStatus listings.
 */
final class Version20260526060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the PurchaseOrder + PurchaseOrderLine persistence schema (LRA-77).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (CHAR(36), TIMESTAMP(0) WITHOUT TIME ZONE, CHECK constraint).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_purchase_orders (
                id CHAR(36) NOT NULL,
                vendor_id CHAR(36) NOT NULL,
                facility_code VARCHAR(32) NOT NULL,
                status VARCHAR(32) NOT NULL,
                sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                estimated_arrival TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                verified_by VARCHAR(36) DEFAULT NULL,
                verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_inventory_po_vendor_status ON inventory_purchase_orders (vendor_id, status)');
        $this->addSql(
            'CREATE INDEX IDX_inventory_po_facility_status '
            . 'ON inventory_purchase_orders (facility_code, status)',
        );
        $this->addSql(
            'CREATE INDEX IDX_inventory_po_status_created '
            . 'ON inventory_purchase_orders (status, created_at)',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_purchase_order_lines (
                id CHAR(36) NOT NULL,
                purchase_order_id CHAR(36) NOT NULL,
                item_id CHAR(36) NOT NULL,
                ordered_quantity INT NOT NULL,
                received_quantity INT NOT NULL DEFAULT 0,
                cost_per_unit_cents BIGINT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_inventory_po_lines_order
                    FOREIGN KEY (purchase_order_id) REFERENCES inventory_purchase_orders (id)
                    ON DELETE CASCADE,
                CONSTRAINT CK_inventory_po_lines_ordered_positive
                    CHECK (ordered_quantity > 0),
                CONSTRAINT CK_inventory_po_lines_received_non_negative
                    CHECK (received_quantity >= 0),
                CONSTRAINT CK_inventory_po_lines_received_le_ordered
                    CHECK (received_quantity <= ordered_quantity)
            )
        SQL);
        $this->addSql(
            'CREATE INDEX IDX_inventory_po_lines_order '
            . 'ON inventory_purchase_order_lines (purchase_order_id)',
        );
        $this->addSql('CREATE INDEX IDX_inventory_po_lines_item ON inventory_purchase_order_lines (item_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS inventory_purchase_order_lines');
        $this->addSql('DROP TABLE IF EXISTS inventory_purchase_orders');
    }
}
