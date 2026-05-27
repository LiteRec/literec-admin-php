<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-95: introduce the Combo persistence schema.
 *
 * Replaces the in-memory fail-loud UnimplementedCombos production
 * binding with a Postgres-backed adapter. ComboComponentEntity is the
 * Doctrine-mapped child of Combo; composite key (combo_id, item_id)
 * matches the LRA-80 plan.
 */
final class Version20260528010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inventory_combos + inventory_combo_components (LRA-95, closes LRA-80 deferral).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (CHAR(36), TIMESTAMP(0) WITHOUT TIME ZONE, BOOLEAN, FK CASCADE).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_combos (
                id CHAR(36) NOT NULL,
                listing_id CHAR(36) NOT NULL,
                archived BOOLEAN DEFAULT false NOT NULL,
                defined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_inventory_combos_listing '
            . 'ON inventory_combos (listing_id)',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_combo_components (
                combo_id CHAR(36) NOT NULL,
                item_id CHAR(36) NOT NULL,
                qty_per_combo INT NOT NULL,
                CONSTRAINT CHK_inventory_combo_components_qty CHECK (qty_per_combo > 0),
                PRIMARY KEY (combo_id, item_id)
            )
        SQL);
        $this->addSql(
            'CREATE INDEX IDX_inventory_combo_components_item '
            . 'ON inventory_combo_components (item_id)',
        );
        $this->addSql(
            'ALTER TABLE inventory_combo_components ADD CONSTRAINT FK_inventory_combo_components_combo '
            . 'FOREIGN KEY (combo_id) REFERENCES inventory_combos (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS inventory_combo_components');
        $this->addSql('DROP TABLE IF EXISTS inventory_combos');
    }
}
