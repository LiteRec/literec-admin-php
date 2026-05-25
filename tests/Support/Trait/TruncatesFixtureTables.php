<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use Doctrine\DBAL\Connection;

/**
 * Truncates every table written by the Users and Households fixtures.
 *
 * Tests that load fixtures (UsersFixtures, HouseholdsFixtures) need
 * the relevant tables to start empty so unique-username and unique
 * row-count assertions hold even when an earlier composer db:reset-test
 * (or a sibling slow test) populated the database.
 */
trait TruncatesFixtureTables
{
    private function truncateFixtureTables(Connection $connection): void
    {
        $connection->executeStatement(
            'TRUNCATE household_residency_history, household_members, households, "user" '
            . 'RESTART IDENTITY CASCADE'
        );
    }
}
