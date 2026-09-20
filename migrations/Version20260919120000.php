<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-268 (slice a): introduce the Administration Role persistence schema.
 *
 * Creates administration_roles backing the {@see App\Administration\Domain\Role}
 * aggregate. privileges is a JSONB column (the administration_privilege_set
 * custom type re-validates and hydrates a PrivilegeSet VO on read/write —
 * same JsonType-over-JSONB pattern as inventory_item_groups.facility_scope,
 * Version20260528020000).
 *
 * Indexes:
 *   - UNIQUE on name so DoctrineRoles::add()/save() can surface a
 *     collision via UniqueConstraintViolationException → DuplicateRoleName.
 *   - GIN on privileges so "which roles grant this privilege" is an
 *     index lookup rather than a scan. Declared here rather than in the
 *     ORM XML mapping because Doctrine's schema tooling has no
 *     first-class GIN index type — same treatment as the Inventory
 *     vendors pg_trgm index (Version20260525175800).
 *
 * No legacy data is loaded or migrated — this creates schema only. The
 * legacy `security_roles` / `security_role_privileges` tables are read
 * as evidence during planning, never as a data source.
 */
final class Version20260919120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Administration schema: administration_roles backing the Role aggregate (LRA-268).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (JSONB, GIN index, TIMESTAMP(0) WITHOUT TIME ZONE).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE administration_roles (
                id          CHAR(36)    NOT NULL,
                name        VARCHAR(80) NOT NULL,
                description TEXT        DEFAULT '' NOT NULL,
                privileges  JSONB       DEFAULT '[]' NOT NULL,
                retired     BOOLEAN     DEFAULT false NOT NULL,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                version     INTEGER     DEFAULT 0 NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_administration_roles_name ON administration_roles (name)');
        $this->addSql(
            'CREATE INDEX idx_administration_roles_privileges '
            . 'ON administration_roles USING GIN (privileges)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS administration_roles');
    }
}
