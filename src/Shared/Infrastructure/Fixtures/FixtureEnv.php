<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Fixtures;

/**
 * Shared env-reading helpers for fixture loaders.
 *
 * Lives under Shared/ because both Users and Households fixtures need
 * the same FIXTURE_SEED and per-context FIXTURE_*_COUNT semantics, and
 * Deptrac forbids cross-context imports between Users and Households.
 */
final class FixtureEnv
{
    public static function seed(int $default = 1): int
    {
        $raw = $_ENV['FIXTURE_SEED'] ?? $_SERVER['FIXTURE_SEED'] ?? null;
        if ($raw === null || $raw === '') {
            return $default;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);

        return $value === false ? $default : $value;
    }

    public static function bulkCount(string $envKey, int $default, int $max): int
    {
        $raw = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? null;
        if ($raw === null || $raw === '') {
            return $default;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            return $default;
        }

        return min($value, $max);
    }
}
