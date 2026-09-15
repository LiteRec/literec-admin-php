<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-210: create household_member_affiliations, the join table backing a
 * minor member's shared-household links (e.g. shared custody).
 *
 * One row per (member, household) link; member_id references the member's
 * home household_members row (the only place identity data — name, DOB,
 * gender, contact, residency, active flag — lives). The index on
 * household_id supports roster lookups by the viewed (possibly non-home)
 * household.
 */
final class Version20260914220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create household_member_affiliations for minor shared-household links (LRA-210).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-210 requires PostgreSQL.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE household_member_affiliations (
                member_id CHAR(36) NOT NULL REFERENCES household_members(id) ON DELETE CASCADE,
                household_id CHAR(36) NOT NULL REFERENCES households(id) ON DELETE CASCADE,
                linked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (member_id, household_id)
            )
        SQL);
        $this->addSql(
            'CREATE INDEX IDX_household_member_affiliations_household ON household_member_affiliations (household_id)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-210 requires PostgreSQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS household_member_affiliations');
    }
}
