<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-208: add merge state to household_members and create the shared
 * household_member_lineage audit table.
 *
 * merged_into_member_id / merged_at follow the household_residency_history
 * precedent: no FK on merged_into_member_id (mirrors
 * household_residency_history.member_id) since it references a row in the
 * same table rather than a distinct aggregate.
 *
 * household_member_lineage is shared with LRA-209 (split): kind is backed
 * by the PHP enum App\Households\Domain\ValueObject\MemberLineageKind
 * (MergedInto = 'MERGED_INTO', SplitFrom = 'SPLIT_FROM') — VARCHAR + PHP
 * enum, not a Postgres enum, matching residency_status. This migration
 * creates the table and enum and writes only MERGED_INTO rows (member =
 * duplicate, related = survivor); LRA-209 adds the SPLIT_FROM writer.
 * transaction_ids JSONB is reserved for LRA-209's future use (split
 * records which transaction references moved); merge rows leave it NULL.
 */
final class Version20260914201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merge state to household_members and create household_member_lineage (LRA-208).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-208 requires PostgreSQL.',
        );

        $this->addSql(
            'ALTER TABLE household_members '
            . 'ADD COLUMN merged_into_member_id CHAR(36) DEFAULT NULL, '
            . 'ADD COLUMN merged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL',
        );
        $this->addSql(
            'CREATE INDEX IDX_household_members_merged_into ON household_members (merged_into_member_id)',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE household_member_lineage (
                id BIGSERIAL NOT NULL,
                household_id CHAR(36) NOT NULL,
                member_id CHAR(36) NOT NULL,
                related_household_id CHAR(36) NOT NULL,
                related_member_id CHAR(36) NOT NULL,
                kind VARCHAR(32) NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                transaction_ids JSONB DEFAULT NULL,
                recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_household_member_lineage_member ON household_member_lineage (member_id)');
        $this->addSql(
            'CREATE INDEX IDX_household_member_lineage_related_member ON household_member_lineage (related_member_id)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-208 requires PostgreSQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS household_member_lineage');
        $this->addSql(
            'ALTER TABLE household_members '
            . 'DROP COLUMN IF EXISTS merged_into_member_id, '
            . 'DROP COLUMN IF EXISTS merged_at',
        );
    }
}
