<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use Doctrine\DBAL\Connection;

/**
 * Truncates every table written by the Users, Households, and
 * Administration fixtures.
 *
 * Tests that load fixtures (UsersFixtures, HouseholdsFixtures,
 * AdministrationFixtures) need the relevant tables to start empty so
 * unique-username, unique-rank-name, and unique row-count assertions
 * hold even when an earlier composer db:reset-test (or a sibling slow
 * test) populated the database.
 */
trait TruncatesFixtureTables
{
    private function truncateFixtureTables(Connection $connection): void
    {
        $connection->executeStatement(
            'TRUNCATE household_residency_history, household_members, households, "user", '
            . 'administration_administrator_roles, administration_tenures, administration_administrators, '
            . 'administration_rank_roles, administration_ranks, administration_roles '
            . 'RESTART IDENTITY CASCADE'
        );
    }
}
