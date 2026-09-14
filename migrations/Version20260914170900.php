<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-205: add nickname, salutation, height, and weight to
 * household_members so the Profile card can capture the remaining
 * "User Management Testing" QA checklist fields.
 */
final class Version20260914170900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nickname, salutation, height_inches, weight_pounds to household_members (LRA-205).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-205 requires PostgreSQL.',
        );

        $this->addSql(
            'ALTER TABLE household_members '
            . 'ADD COLUMN nickname VARCHAR(128) DEFAULT NULL, '
            . 'ADD COLUMN salutation VARCHAR(8) DEFAULT NULL, '
            . 'ADD COLUMN height_inches SMALLINT DEFAULT NULL '
            . 'CONSTRAINT CHK_household_members_height_inches '
            . 'CHECK (height_inches IS NULL OR height_inches BETWEEN 1 AND 107), '
            . 'ADD COLUMN weight_pounds SMALLINT DEFAULT NULL '
            . 'CONSTRAINT CHK_household_members_weight_pounds '
            . 'CHECK (weight_pounds IS NULL OR weight_pounds BETWEEN 1 AND 1500)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'LRA-205 requires PostgreSQL.',
        );

        $this->addSql(
            'ALTER TABLE household_members '
            . 'DROP COLUMN IF EXISTS nickname, '
            . 'DROP COLUMN IF EXISTS salutation, '
            . 'DROP COLUMN IF EXISTS height_inches, '
            . 'DROP COLUMN IF EXISTS weight_pounds',
        );
    }
}
