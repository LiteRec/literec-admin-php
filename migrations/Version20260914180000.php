<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-207: add profile-photo columns to household_members so a member's
 * uploaded photo can replace the initials-avatar fallback on the member
 * detail page and the Users list row.
 */
final class Version20260914180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photo_storage_key, photo_format, photo_uploaded_at to household_members (LRA-207).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-207 requires PostgreSQL.',
        );

        $this->addSql(
            'ALTER TABLE household_members '
            . 'ADD COLUMN photo_storage_key VARCHAR(255) DEFAULT NULL, '
            . 'ADD COLUMN photo_format VARCHAR(16) DEFAULT NULL, '
            . 'ADD COLUMN photo_uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-207 requires PostgreSQL.',
        );

        $this->addSql(
            'ALTER TABLE household_members '
            . 'DROP COLUMN IF EXISTS photo_storage_key, '
            . 'DROP COLUMN IF EXISTS photo_format, '
            . 'DROP COLUMN IF EXISTS photo_uploaded_at',
        );
    }
}
