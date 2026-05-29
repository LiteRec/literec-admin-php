<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * Null-safe value-object equality for Inventory aggregates.
 *
 * Two nullable references are equal when both are null, or both are non-null
 * and the supplied comparator reports them equal. Lets an aggregate compare an
 * optional value object (vendor email, phone, address, primary vendor id)
 * without repeating the null-handling ladder inside every update method.
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
