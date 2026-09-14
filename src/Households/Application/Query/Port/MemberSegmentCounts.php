<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Count-per-segment projection backing the Users list filter pills
 * (LRA-192). Every count is scoped to the current free-text `q` only —
 * the detailed "More filters" fields do not narrow these counts, so the
 * pills always answer "how many would this segment show if I cleared
 * the advanced filters".
 */
final readonly class MemberSegmentCounts
{
    public function __construct(
        public int $all,
        public int $residents,
        public int $nonResidents,
        public int $inactive,
    ) {
    }
}
