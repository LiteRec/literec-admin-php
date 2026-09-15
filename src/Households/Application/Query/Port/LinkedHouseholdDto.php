<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * One entry in {@see MemberDetail::$linkedHouseholds} (LRA-210): the home
 * household plus every household the member has been shared with.
 * `linkedAtIso` is null for the home entry (`isHome` true) and set for
 * every shared entry.
 */
final readonly class LinkedHouseholdDto
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public bool $isHome,
        public ?string $linkedAtIso,
    ) {
    }
}
