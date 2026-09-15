<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-213: add password_state to "user", tracking the one-time-password
 * credential lifecycle (established / one_time_issued / one_time_consumed).
 * Additive column with a default, so it is safe to roll forward over the
 * persistent E2E database per docs/e2e-migrations.md.
 */
final class Version20260915100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password_state to "user" (LRA-213).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-213 requires PostgreSQL.',
        );

        $this->addSql(
            'ALTER TABLE "user" '
            . "ADD COLUMN password_state VARCHAR(32) NOT NULL DEFAULT 'established'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-213 requires PostgreSQL.',
        );

        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS password_state');
    }
}
