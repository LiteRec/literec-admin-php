<?php

declare(strict_types=1);

namespace App\Households\Application\Query;

/**
 * Query bus message backing the Users list filter pill counts (LRA-192).
 * Carries only the free-text `q` term — see
 * {@see \App\Households\Application\Query\Port\MemberReadModel::segmentCounts()}
 * for why the detailed filters do not apply here.
 */
final readonly class CountMemberSegments
{
    public function __construct(
        public ?string $q,
    ) {
    }
}
