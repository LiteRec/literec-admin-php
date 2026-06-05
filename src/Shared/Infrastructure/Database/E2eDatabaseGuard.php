<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

/**
 * Pure predicate that recognises the dedicated E2E lane database by name.
 *
 * The seed/reset console commands DROP and CREATE databases, so they must
 * never run against the dev or production database (both named `app`, set
 * apart by host/credentials and environment rather than by database name),
 * nor the functional/integration rollback database (`app_test`). The
 * convention from LRA-176 is that the E2E lane name always contains "e2e"
 * (physical name `app_e2e_test`); requiring that token blocks every other lane.
 */
final class E2eDatabaseGuard
{
    private const REQUIRED_TOKEN = 'e2e';

    public static function isE2eDatabase(string $databaseName): bool
    {
        return str_contains(mb_strtolower($databaseName, 'UTF-8'), self::REQUIRED_TOKEN);
    }
}
