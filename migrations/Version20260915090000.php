<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-212: add anonymized_at to household_members, marking a member's
 * identity data as irreversibly scrubbed. Follows the deactivated_at /
 * merged_at precedent: a plain nullable timestamp, no FK, set once and
 * never cleared.
 */
final class Version20260915090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add anonymized_at to household_members (LRA-212).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-212 requires PostgreSQL.',
        );

        $this->addSql(
            'ALTER TABLE household_members '
            . 'ADD COLUMN anonymized_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-212 requires PostgreSQL.',
        );

        $this->addSql('ALTER TABLE household_members DROP COLUMN IF EXISTS anonymized_at');
    }
}
