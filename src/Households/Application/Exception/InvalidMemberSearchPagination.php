<?php

declare(strict_types=1);

namespace App\Households\Application\Exception;

use InvalidArgumentException;

/**
 * Raised when {@see \App\Households\Application\Query\Port\SearchMembersCriteria}
 * is built with a page or pageSize outside its bounded range.
 *
 * Application-layer exception, not a Domain one: SearchMembersCriteria is a
 * primitive-only query DTO (CLAUDE.md: DTOs carry primitives and never
 * Domain types), and LRA-200's PHPat rule forbids command/query DTOs from
 * depending on any App\*\Domain namespace — implementing a Domain marker
 * interface here would count as that dependency. Extending
 * InvalidArgumentException stays allowed: LRA-199's rule forbids
 * *constructing* SPL exception types from Domain/Application code, not
 * extending them.
 */
final class InvalidMemberSearchPagination extends InvalidArgumentException
{
    public static function pageBelowMinimum(int $min): self
    {
        return new self(sprintf('SearchMembersCriteria: page must be >= %d.', $min));
    }

    public static function pageSizeOutOfRange(int $min, int $max): self
    {
        return new self(sprintf('SearchMembersCriteria: pageSize must be in [%d, %d].', $min, $max));
    }
}
