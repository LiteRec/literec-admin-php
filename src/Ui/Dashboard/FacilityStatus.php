<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * One row in the Facility Status widget: a facility, its current condition as
 * a badge, and today's visitor count. The badge variant is one of the shared
 * lr-badge variants (success | info | warning | danger | neutral); the _badge
 * partial sanitises anything else to neutral. Presentation-only sample data
 * until a Facilities read model comes online.
 */
final readonly class FacilityStatus
{
    public function __construct(
        public string $name,
        public string $conditionLabel,
        public string $badgeVariant,
        public int $visitorsToday,
    ) {
        if ($visitorsToday < 0) {
            throw new \InvalidArgumentException('Visitor count cannot be negative.');
        }
    }
}
