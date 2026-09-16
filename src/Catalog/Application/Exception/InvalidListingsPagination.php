<?php

declare(strict_types=1);

namespace App\Catalog\Application\Exception;

use InvalidArgumentException;

/**
 * Raised when {@see \App\Catalog\Application\Query\FindListingsByKind} is
 * built with an out-of-range offset or limit.
 *
 * Application-layer exception, not a Domain one: FindListingsByKind is a
 * primitive-only query DTO (CLAUDE.md: DTOs carry primitives and never
 * Domain types), and LRA-200's PHPat rule forbids command/query DTOs from
 * depending on any App\*\Domain namespace — implementing a Domain marker
 * interface here would count as that dependency. Extending
 * InvalidArgumentException stays allowed: LRA-199's rule forbids
 * *constructing* SPL exception types from Domain/Application code, not
 * extending them.
 */
final class InvalidListingsPagination extends InvalidArgumentException
{
    public static function negativeOffset(int $offset): self
    {
        return new self(sprintf('Pagination offset must be non-negative; got %d.', $offset));
    }

    public static function limitBelowOne(int $limit): self
    {
        return new self(sprintf('Pagination limit must be at least 1; got %d.', $limit));
    }
}
