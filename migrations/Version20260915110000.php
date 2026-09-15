<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-213 follow-up (review round): add an optimistic-lock "version" column
 * so two logins racing to consume the same one-time password cannot both
 * succeed, and "one_time_password_issued_at" so UserChecker can reject a
 * one-time password past its TTL. Both are additive with defaults, so this
 * is safe to roll forward over the persistent E2E database per
 * docs/e2e-migrations.md.
 */
final class Version20260915110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add version and one_time_password_issued_at to "user" (LRA-213).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-213 requires PostgreSQL.',
        );

        $this->addSql(
            'ALTER TABLE "user" '
            . 'ADD COLUMN version INT NOT NULL DEFAULT 0, '
            . 'ADD COLUMN one_time_password_issued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-213 requires PostgreSQL.',
        );

        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS one_time_password_issued_at');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS version');
    }
}
