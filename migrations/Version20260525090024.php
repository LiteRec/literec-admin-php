<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LRA-66: introduce the Catalog persistence schema.
 *
 * Creates the catalog_listings table backing the {@see App\Catalog\Domain\Listing}
 * aggregate. Fees and tax treatment are persisted inline as JSONB columns
 * (custom DBAL types convert to and from the matching value objects); GL
 * account, kind, and code are typed strings.
 *
 * Indexes:
 *   - UNIQUE on code so DoctrineListings::add() can surface duplicates
 *     via UniqueConstraintViolationException → DuplicateListingCode.
 *   - kind to back findByKind() pagination.
 *   - archived to back the active-only browse paths in the read-side
 *     queries that land in LRA-68.
 */
final class Version20260525090024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Catalog schema: catalog_listings backing the Listing aggregate (LRA-66).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL (JSONB, BOOLEAN, TIMESTAMP(0) WITHOUT TIME ZONE).',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog_listings (
                id CHAR(36) NOT NULL,
                code VARCHAR(32) NOT NULL,
                kind VARCHAR(16) NOT NULL,
                name VARCHAR(255) NOT NULL,
                fees JSONB NOT NULL,
                tax_treatment JSONB NOT NULL,
                ledger_account VARCHAR(16) NOT NULL,
                archived BOOLEAN DEFAULT false NOT NULL,
                registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_catalog_listings_code ON catalog_listings (code)');
        $this->addSql('CREATE INDEX IDX_catalog_listings_kind ON catalog_listings (kind)');
        $this->addSql('CREATE INDEX IDX_catalog_listings_archived ON catalog_listings (archived)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            ! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration uses PostgreSQL-specific SQL.',
        );

        $this->addSql('DROP TABLE IF EXISTS catalog_listings');
    }
}
