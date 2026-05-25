<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-72: introduce the Inventory Vendor persistence schema.
 *
 * Creates the inventory_vendors table backing the {@see App\Inventory\Domain\Vendor}
 * aggregate. The vendor address is persisted as a single nullable JSONB
 * column (the inventory_vendor_address custom type re-validates and
 * hydrates a VendorAddress VO) so optional addresses do not require
 * weakening the VO invariant.
 *
 * Indexes:
 *   - UNIQUE on code so DoctrineVendors::add() can surface duplicates
 *     via UniqueConstraintViolationException → DuplicateVendorCode.
 *   - archived to back the listActive() browse path.
 *   - functional index on LOWER(name) to back the searchByName() path,
 *     which uses LOWER(...) LIKE ... ESCAPE '!' to perform a
 *     case-insensitive partial match.
 */
final class Version20260525175800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Inventory schema: inventory_vendors backing the Vendor aggregate (LRA-72).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (JSONB, BOOLEAN, TIMESTAMP(0) WITHOUT TIME ZONE, functional LOWER() index).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_vendors (
                id CHAR(36) NOT NULL,
                code VARCHAR(32) NOT NULL,
                name VARCHAR(100) NOT NULL,
                contact VARCHAR(100) NOT NULL,
                email VARCHAR(254) DEFAULT NULL,
                phone VARCHAR(32) DEFAULT NULL,
                address JSONB DEFAULT NULL,
                archived BOOLEAN DEFAULT false NOT NULL,
                registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_inventory_vendors_code ON inventory_vendors (code)');
        $this->addSql('CREATE INDEX IDX_inventory_vendors_archived ON inventory_vendors (archived)');
        $this->addSql('CREATE INDEX IDX_inventory_vendors_name_lower ON inventory_vendors (LOWER(name))');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS inventory_vendors');
    }
}
