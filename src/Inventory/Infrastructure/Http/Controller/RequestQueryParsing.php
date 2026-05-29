<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Query-string parsing helpers shared by the Inventory report controllers.
 *
 * Operators drive the report and listing pages entirely through GET query
 * parameters, so the same trim-or-null and strict YYYY-MM-DD date coercions
 * recur across several controllers. Centralising them here keeps the parsing
 * rules identical (a malformed value disables its own filter rather than
 * 400-ing the page) and keeps each controller focused on its actions.
 */
trait RequestQueryParsing
{
    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Parses a YYYY-MM-DD operator input into a UTC `DateTimeImmutable`.
     * Returns null on any parse failure so a malformed filter just disables
     * itself rather than 400-ing the page.
     */
    private static function parseDate(?string $raw, bool $startOfDay): ?DateTimeImmutable
    {
        if ($raw === null) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $raw,
            new DateTimeZone('UTC'),
        );

        // createFromFormat silently normalizes invalid dates like
        // 2026-02-31 -> 2026-03-03 and still returns a DateTimeImmutable, so
        // round-trip back to the same Y-m-d string to confirm strict
        // validity. A parse failure or any mutation disables the filter.
        if ($parsed === false || $parsed->format('Y-m-d') !== $raw) {
            return null;
        }

        return $startOfDay
            ? $parsed->setTime(0, 0, 0)
            : $parsed->setTime(23, 59, 59);
    }
}
