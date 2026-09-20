<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-269: introduce the Administration Administrator persistence schema.
 *
 * Creates administration_administrators backing the
 * {@see App\Administration\Domain\Administrator} aggregate,
 * administration_tenures (its append-only lifecycle history), and
 * administration_administrator_roles (its directly assigned roles).
 * No legacy data is loaded or migrated — this creates schema only. The
 * legacy `user_accounts.rank` nullable column is read as evidence during
 * planning, never as a data source.
 *
 * administration_administrators:
 *   - UNIQUE INDEX on sign_in_account_id: a sign-in account holds at
 *     most one administrator record for its entire lifetime. No foreign
 *     key to the Users context's `user` table — separate bounded
 *     contexts never share a foreign key.
 *   - rank_id carries no foreign key either: Rank is a separate
 *     aggregate within this same context, and the project forbids
 *     Doctrine associations (and, by the same precedent
 *     administration_rank_roles.role_id sets, foreign keys) across an
 *     aggregate boundary.
 *   - standing is a denormalised projection of "is the newest tenure
 *     open" so a lookup by sign-in account can filter without loading
 *     every tenure.
 *
 * administration_tenures:
 *   - administrator_id has ON DELETE CASCADE to administration_administrators
 *     — Administrator owns this child table; nothing else references a
 *     tenure row.
 *   - granted_by_* / revoked_by_* mirror the three-way actor CHECK
 *     constraint shape administration_rank_roles already uses, one set
 *     per Actor embedded on the entity (grantedBy, revokedBy).
 *     granted_by_kind is always populated (every tenure has a grantor);
 *     the revoked_by_* triple, revoked_at, and revocation_reason are all
 *     null together while the tenure is open and all populated together
 *     once closed — enforced by CHK_administration_tenures_revoked_by_actor
 *     rather than left to application code alone.
 *
 * administration_administrator_roles:
 *   - Composite primary key (administrator_id, role_id) makes a double
 *     assignment of the same role to the same administrator impossible
 *     at the schema level.
 *   - Standalone index on role_id serves Administrators::activeHoldersOfRole().
 *   - role_id carries no foreign key: Role is a separate aggregate, same
 *     precedent as administration_rank_roles.role_id.
 *   - administrator_id has ON DELETE CASCADE to administration_administrators.
 *
 * A reversible down() drops all three tables, children first.
 */
final class Version20260920100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Administration schema: administration_administrators, administration_tenures, and '
            . 'administration_administrator_roles backing the Administrator aggregate (LRA-269).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (TIMESTAMP(0) WITHOUT TIME ZONE, CHECK constraint).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE administration_administrators (
                id                  CHAR(36)    NOT NULL PRIMARY KEY,
                sign_in_account_id  CHAR(36)    NOT NULL,
                rank_id             CHAR(36)    NOT NULL,
                standing            VARCHAR(20) NOT NULL,
                updated_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                version             INT         NOT NULL DEFAULT 0
            )
            SQL);

        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_administration_administrators_sign_in_account '
            . 'ON administration_administrators (sign_in_account_id)',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE administration_tenures (
                id                              CHAR(36) NOT NULL PRIMARY KEY,
                administrator_id                CHAR(36) NOT NULL
                    REFERENCES administration_administrators (id) ON DELETE CASCADE,
                granted_at                      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                granted_by_kind                 VARCHAR(20)  NOT NULL,
                granted_by_administrator_id     CHAR(36)     DEFAULT NULL,
                granted_by_sign_in_account_id   CHAR(36)     DEFAULT NULL,
                revoked_at                      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                revoked_by_kind                 VARCHAR(20)  DEFAULT NULL,
                revoked_by_administrator_id     CHAR(36)     DEFAULT NULL,
                revoked_by_sign_in_account_id   CHAR(36)     DEFAULT NULL,
                revocation_reason               VARCHAR(255) DEFAULT NULL,
                CONSTRAINT CHK_administration_tenures_granted_by_actor CHECK (
                    (granted_by_kind = 'ADMINISTRATOR'
                        AND granted_by_administrator_id IS NOT NULL
                        AND granted_by_sign_in_account_id IS NULL)
                 OR (granted_by_kind = 'SIGN_IN_ACCOUNT'
                        AND granted_by_sign_in_account_id IS NOT NULL
                        AND granted_by_administrator_id IS NULL)
                 OR (granted_by_kind = 'SYSTEM'
                        AND granted_by_administrator_id IS NULL
                        AND granted_by_sign_in_account_id IS NULL)
                ),
                CONSTRAINT CHK_administration_tenures_revoked_by_actor CHECK (
                    (revoked_by_kind IS NULL
                        AND revoked_at IS NULL
                        AND revocation_reason IS NULL
                        AND revoked_by_administrator_id IS NULL
                        AND revoked_by_sign_in_account_id IS NULL)
                 OR (revoked_by_kind = 'ADMINISTRATOR'
                        AND revoked_at IS NOT NULL
                        AND revocation_reason IS NOT NULL
                        AND revoked_by_administrator_id IS NOT NULL
                        AND revoked_by_sign_in_account_id IS NULL)
                 OR (revoked_by_kind = 'SIGN_IN_ACCOUNT'
                        AND revoked_at IS NOT NULL
                        AND revocation_reason IS NOT NULL
                        AND revoked_by_sign_in_account_id IS NOT NULL
                        AND revoked_by_administrator_id IS NULL)
                 OR (revoked_by_kind = 'SYSTEM'
                        AND revoked_at IS NOT NULL
                        AND revocation_reason IS NOT NULL
                        AND revoked_by_administrator_id IS NULL
                        AND revoked_by_sign_in_account_id IS NULL)
                )
            )
            SQL);

        $this->addSql(
            'CREATE INDEX IDX_administration_tenures_administrator ON administration_tenures (administrator_id)',
        );

        // Enforces "at most one open tenure per administrator" at the
        // database level — the invariant standing() and currentTenure()
        // both rest on — the same way the two CHECK constraints above
        // enforce the actor pairing rather than leaving it to
        // application code alone. Doctrine's XML mapping cannot express
        // a partial unique index, so this stays migration-only; a
        // future schema diff must not drop it.
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_administration_tenures_open '
            . 'ON administration_tenures (administrator_id) WHERE revoked_at IS NULL',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE administration_administrator_roles (
                administrator_id  CHAR(36) NOT NULL
                    REFERENCES administration_administrators (id) ON DELETE CASCADE,
                role_id           CHAR(36) NOT NULL,
                assigned_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (administrator_id, role_id)
            )
            SQL);

        $this->addSql(
            'CREATE INDEX IDX_administration_administrator_roles_role '
            . 'ON administration_administrator_roles (role_id)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS administration_administrator_roles');
        $this->addSql('DROP TABLE IF EXISTS administration_tenures');
        $this->addSql('DROP TABLE IF EXISTS administration_administrators');
    }
}
