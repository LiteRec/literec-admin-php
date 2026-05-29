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

        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowNullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        return $this->scalarToInt($value);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowBool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;

        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_string($value) => $value !== '' && $value !== '0'
                && strtolower($value) !== 'f'
                && strtolower($value) !== 'false',
            default => false,
        };
    }

    private function scalarToInt(mixed $value): int
    {
        return match (true) {
            is_int($value) => $value,
            is_string($value) && is_numeric($value), is_float($value) => (int) $value,
            is_bool($value) => $value ? 1 : 0,
            default => 0,
        };
    }
}
