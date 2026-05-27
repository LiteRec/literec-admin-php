<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Read;

/**
 * Typed accessors over `array<string,mixed>` rows returned by DBAL
 * fetch* methods. Lets read-side adapters coerce row values into
 * strict-typed DTO fields without per-call `(string)` / `(int)`
 * casts that PHPStan level 9 rejects.
 *
 * Originally extracted from the Households + Inventory read models
 * (LRA-38 / LRA-97) so the same coercion semantics apply across
 * bounded contexts and SonarCloud's CPD does not flag the helpers
 * as duplicated infrastructure.
 */
trait RowFieldExtraction
{
    /**
     * @param array<string, mixed> $row
     */
    private function rowString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowNullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowBool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            return $value !== '' && $value !== '0' && $lower !== 'f' && $lower !== 'false';
        }
        return false;
    }

    private function scalarToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return 0;
    }
}
