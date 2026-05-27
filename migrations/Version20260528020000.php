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
 * the LRA-97 per-item recent-groups read path. FacilityScope is
 * persisted as JSONB NOT NULL via the inventory_facility_scope
 * Doctrine type.
 */
final class Version20260528020000 extends AbstractMigration
{
    /**
     * Combined DDL kept in one heredoc so the migration body reads as
     * a single schema declaration. Multiple statements per addSql()
     * call go through pdo_pgsql which executes them sequentially as
     * one round-trip.
     */
    private const string FORWARD_DDL = <<<'SQL'
        CREATE TABLE inventory_item_groups (
            id CHAR(36) NOT NULL,
            name VARCHAR(80) NOT NULL,
            color_hex VARCHAR(7) NOT NULL,
            facility_scope JSONB NOT NULL,
            archived BOOLEAN DEFAULT false NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        );
        CREATE UNIQUE INDEX UNIQ_inventory_item_groups_name ON inventory_item_groups (name);
        CREATE TABLE inventory_item_group_members (
            group_id CHAR(36) NOT NULL REFERENCES inventory_item_groups (id) ON DELETE CASCADE,
            item_id CHAR(36) NOT NULL,
            added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (group_id, item_id)
        );
        CREATE INDEX IDX_inventory_item_group_members_item_added
            ON inventory_item_group_members (item_id, added_at DESC);
        SQL;

    private const string REVERSE_DDL = <<<'SQL'
        DROP TABLE IF EXISTS inventory_item_group_members;
        DROP TABLE IF EXISTS inventory_item_groups;
        SQL;

    public function getDescription(): string
    {
        return 'Create inventory_item_groups + inventory_item_group_members (LRA-96).';
    }

    public function up(Schema $schema): void
    {
        $this->guardPostgres();
        $this->addSql(self::FORWARD_DDL);
    }

    public function down(Schema $schema): void
    {
        $this->guardPostgres();
        $this->addSql(self::REVERSE_DDL);
    }

    private function guardPostgres(): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-96 migration requires PostgreSQL (JSONB, CHAR(36), partial-by-default unique on a single column).',
        );
    }
}
