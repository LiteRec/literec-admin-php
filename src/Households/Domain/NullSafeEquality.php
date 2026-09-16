<?php

declare(strict_types=1);

namespace App\Households\Domain;

/**
 * Null-safe value-object equality for Households value objects (LRA-237).
 *
 * Two nullable references are equal when both are null, or both are non-null
 * and the supplied comparator reports them equal. Lets a value object's
 * equals() compare an optional field (height, weight, deactivation, merge)
 * without repeating the null-handling ladder in every equals() method.
 *
 * Deliberately not {@see \App\Inventory\Domain\NullSafeEquality}: sharing a
 * trait across bounded contexts would be a cross-context dependency the
 * architecture rules forbid, so Households owns an identical trait instead.
 */
trait NullSafeEquality
{
    /**
     * @template T of object
     *
     * @param T|null               $a
     * @param T|null               $b
     * @param callable(T, T): bool $compare
     */
    private static function nullSafeEquals(?object $a, ?object $b, callable $compare): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        return $compare($a, $b);
    }
}
