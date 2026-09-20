<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-267: introduce the Administration Rank persistence schema.
 *
 * Creates administration_ranks backing the {@see App\Administration\Domain\Rank}
 * aggregate, and administration_rank_roles, the join table recording
 * which roles each rank currently grants.
 *
 * administration_ranks:
 *   - Plain UNIQUE INDEX on name (not a functional LOWER(name) index like
 *     administration_roles): RankName::equals() is case-sensitive (see
 *     that class's docblock), so a byte-exact constraint matches the
 *     write-time predicate.
 *   - Plain INDEX on seniority: ranks are listed ordered by it.
 *
 * administration_rank_roles:
 *   - Composite primary key (rank_id, role_id) makes a double assignment
 *     of the same role to the same rank impossible at the schema level.
 *   - Standalone index on role_id serves Ranks::listGrantingRole().
 *   - role_id carries no foreign key: the roles table belongs to LRA-268,
 *     and a cross-table constraint here would couple the two tickets'
 *     migration ordering.
 *   - rank_id has ON DELETE CASCADE to administration_ranks — Rank owns
 *     this join table; nothing else references a rank row.
 *   - The join row records who made the assignment and when, under the
 *     same three-way CHECK-constraint shape LRA-273 uses on its audit
 *     table, so exactly one of the assigned_by_* columns is populated
 *     per assigned_by_kind. The row is the *current* holder of each
 *     assignment; LRA-273's audit log remains the history of every grant
 *     and revocation over time.
 *
 * A reversible down() drops both tables, the join table first. Per
 * CLAUDE.md the Administration context owns its own tables. No legacy
 * data is loaded or migrated — this creates schema only. The legacy
 * `admin_ranks` / `admin_rank_roles` / `admin_rank_privileges` tables are
 * read as evidence during planning, never as a data source.
 */
final class Version20260920093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Administration schema: administration_ranks and administration_rank_roles '
            . 'backing the Rank aggregate (LRA-267).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (TIMESTAMP(0) WITHOUT TIME ZONE, CHECK constraint).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE administration_ranks (
                id          CHAR(36)    NOT NULL PRIMARY KEY,
                name        VARCHAR(45) NOT NULL,
                seniority   SMALLINT    NOT NULL,
                retired     BOOLEAN     NOT NULL DEFAULT FALSE,
                defined_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                version     INT         NOT NULL DEFAULT 0
            )
            SQL);

        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_administration_ranks_name ON administration_ranks (name)',
        );
        $this->addSql(
            'CREATE INDEX IDX_administration_ranks_seniority ON administration_ranks (seniority)',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE administration_rank_roles (
                rank_id                        CHAR(36) NOT NULL REFERENCES administration_ranks (id) ON DELETE CASCADE,
                role_id                        CHAR(36) NOT NULL,
                assigned_at                    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                assigned_by_kind               VARCHAR(20) NOT NULL,
                assigned_by_administrator_id   CHAR(36) DEFAULT NULL,
                assigned_by_sign_in_account_id CHAR(36) DEFAULT NULL,
                PRIMARY KEY (rank_id, role_id),
                CONSTRAINT CHK_administration_rank_roles_actor CHECK (
                    (assigned_by_kind = 'administrator'
                        AND assigned_by_administrator_id IS NOT NULL
                        AND assigned_by_sign_in_account_id IS NULL)
                 OR (assigned_by_kind = 'sign_in_account'
                        AND assigned_by_sign_in_account_id IS NOT NULL
                        AND assigned_by_administrator_id IS NULL)
                 OR (assigned_by_kind = 'system'
                        AND assigned_by_administrator_id IS NULL
                        AND assigned_by_sign_in_account_id IS NULL)
                )
            )
            SQL);

        $this->addSql(
            'CREATE INDEX IDX_administration_rank_roles_role ON administration_rank_roles (role_id)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS administration_rank_roles');
        $this->addSql('DROP TABLE IF EXISTS administration_ranks');
    }
}
