<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Primitive-only projection of a member's current residency status. The
 * effective-from date and reason will be added by LRA-44 once the history
 * table starts being read from; for now the read model exposes only the
 * current status string (matching the column on household_members).
 */
final readonly class MemberResidencyDto
{
    public function __construct(
        public string $status,
    ) {
    }
}
