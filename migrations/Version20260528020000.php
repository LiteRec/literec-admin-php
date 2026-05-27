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
     * @return list<string> Individual DDL statements; doctrine-migrations
     *                     dispatches them through addSql() one at a time
     *                     because Postgres prepared-statement protocol
     *                     does not accept multi-statement strings.
     */
    private static function forwardStatements(): array
    {
        return [
            <<<'SQL'
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
                SQL,
            'CREATE UNIQUE INDEX UNIQ_inventory_item_groups_name ON inventory_item_groups (name)',
            <<<'SQL'
                CREATE TABLE inventory_item_group_members (
                    group_id CHAR(36) NOT NULL REFERENCES inventory_item_groups (id) ON DELETE CASCADE,
                    item_id CHAR(36) NOT NULL,
                    added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    PRIMARY KEY (group_id, item_id)
                )
                SQL,
            'CREATE INDEX IDX_inventory_item_group_members_item_added '
                . 'ON inventory_item_group_members (item_id, added_at DESC)',
        ];
    }

    /**
     * @return list<string>
     */
    private static function reverseStatements(): array
    {
        return [
            'DROP TABLE IF EXISTS inventory_item_group_members',
            'DROP TABLE IF EXISTS inventory_item_groups',
        ];
    }

    public function getDescription(): string
    {
        return 'Create inventory_item_groups + inventory_item_group_members (LRA-96).';
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
            'LRA-96 migration requires PostgreSQL (JSONB, CHAR(36), partial unique semantics on a single column).',
        );
    }
}
