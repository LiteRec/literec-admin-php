<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-37: introduce the Households persistence schema.
 *
 * Creates the four tables (households + household_addresses-equivalent
 * columns embedded inline, household_members, household_residency_history)
 * and the Postgres sequence used by DoctrineMemberCodeAllocator.
 *
 * household_residency_history is created here so the schema is stable for
 * the LRA-37 PR, even though the code that writes to it lands in LRA-44.
 *
 * The Address columns intentionally live on `households` rather than a
 * separate `household_addresses` table: Doctrine ORM 3 has no
 * first-class secondary-table support for embeddables, and Address has
 * no independent identity in the domain. See Household.orm.xml for the
 * mapping side of this decision.
 */
final class Version20260524175153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Households schema: households, household_members, household_residency_history, and the member-code sequence (LRA-37).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (BIGSERIAL, BOOLEAN, TIMESTAMP(0) WITHOUT TIME ZONE, sequences).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE households (
                id CHAR(36) NOT NULL,
                name VARCHAR(200) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                street VARCHAR(255) NOT NULL,
                unit VARCHAR(64) DEFAULT NULL,
                city VARCHAR(128) NOT NULL,
                state VARCHAR(128) NOT NULL,
                postal_code VARCHAR(32) NOT NULL,
                country CHAR(2) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE household_members (
                id CHAR(36) NOT NULL,
                household_id CHAR(36) NOT NULL,
                code VARCHAR(32) NOT NULL,
                first_name VARCHAR(128) NOT NULL,
                middle_name VARCHAR(128) DEFAULT NULL,
                last_name VARCHAR(128) NOT NULL,
                suffix VARCHAR(32) DEFAULT NULL,
                date_of_birth DATE NOT NULL,
                gender CHAR(1) NOT NULL,
                email VARCHAR(254) DEFAULT NULL,
                phone VARCHAR(32) DEFAULT NULL,
                residency_status VARCHAR(32) NOT NULL,
                is_primary BOOLEAN DEFAULT false NOT NULL,
                is_active BOOLEAN DEFAULT true NOT NULL,
                deactivated_reason VARCHAR(255) DEFAULT NULL,
                deactivated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_household_members_household FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_household_members_household_code ON household_members (household_id, code)');
        $this->addSql('CREATE INDEX IDX_household_members_household ON household_members (household_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE household_residency_history (
                id BIGSERIAL NOT NULL,
                household_id CHAR(36) NOT NULL,
                member_id CHAR(36) NOT NULL,
                status VARCHAR(32) NOT NULL,
                effective_from TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_household_residency_history_household FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_household_residency_history_member ON household_residency_history (member_id)');

        $this->addSql('CREATE SEQUENCE household_member_code_seq START WITH 1 INCREMENT BY 1');
    }
}
