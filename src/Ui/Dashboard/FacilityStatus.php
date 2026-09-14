<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * One row in the Facilities today widget: a facility, its current condition
 * as a badge, and today's check-in count. The badge variant is one of the
 * shared lr-badge variants (success | warning | danger | info | neutral);
 * the _badge partial sanitises anything else to neutral. success/warning/
 * neutral double as the Organic sage/accent/neutral status tones (Open =
 * sage, Busy = accent, Maintenance = neutral). Presentation-only sample data
 * until a Facilities read model comes online.
 */
final readonly class FacilityStatus
{
    public function __construct(
        public string $name,
        public string $conditionLabel,
        public string $badgeVariant,
        public int $checkInsToday,
    ) {
        if ($checkInsToday < 0) {
            throw new \InvalidArgumentException('Check-in count cannot be negative.');
        }
    }
}
