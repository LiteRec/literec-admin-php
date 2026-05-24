<?php

declare(strict_types=1);

namespace App\Households\Application\Query;

use App\Households\Application\Query\Port\SearchMembersCriteria;

/**
 * Query bus message for the members search use case. Wraps the criteria
 * DTO so the bus message shape stays simple while criteria remains a
 * separately-testable typed value.
 */
final readonly class SearchMembers
{
    public function __construct(
        public SearchMembersCriteria $criteria,
    ) {
    }
}
