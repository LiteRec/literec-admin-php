<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-96: introduce the ItemGroup persistence schema.
 *
 * Replaces the in-memory fail-loud UnimplementedItemGroups production
 * binding with a Postgres-backed adapter. ItemGroupMembership is the
 * Doctrine-mapped child of ItemGroup; composite key (group_id, item_id)
 * matches the LRA-81 plan. The (item_id, added_at DESC) index backs
 * the LRA-97 per-item recent-groups read path.
 *
 * FacilityScope is persisted as a JSONB column via the
 * inventory_facility_scope custom Doctrine type. The column is
 * declared JSONB NOT NULL because every item group must have a
 * defined facility scope (ALL or a non-empty facility list — never
 * absent), matching the FacilityScope VO's invariant.
 */
final class Version20260528020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inventory_item_groups + inventory_item_group_members (LRA-96, closes LRA-81 deferral).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_item_groups (
                id CHAR(36) NOT NULL,
                name VARCHAR(80) NOT NULL,
                color_hex VARCHAR(7) NOT NULL,
                facility_scope JSONB NOT NULL,
                archived BOOLEAN DEFAULT false NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_inventory_item_groups_name '
            . 'ON inventory_item_groups (name)',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_item_group_members (
                group_id CHAR(36) NOT NULL,
                item_id CHAR(36) NOT NULL,
                added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (group_id, item_id)
            )
        SQL);
        $this->addSql(
            'CREATE INDEX IDX_inventory_item_group_members_item_added '
            . 'ON inventory_item_group_members (item_id, added_at DESC)',
        );
        $this->addSql(
            'ALTER TABLE inventory_item_group_members ADD CONSTRAINT FK_inventory_item_group_members_group '
            . 'FOREIGN KEY (group_id) REFERENCES inventory_item_groups (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS inventory_item_group_members');
        $this->addSql('DROP TABLE IF EXISTS inventory_item_groups');
    }
}
