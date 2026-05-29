<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * One row in the Upcoming Events widget. The date is pre-split into a day
 * number and short month label so the template can render the date chip
 * directly without touching locale APIs. Presentation-only sample data until
 * a Scheduling context comes online.
 */
final readonly class EventItem
{
    public function __construct(
        public string $day,
        public string $month,
        public string $title,
        public string $location,
        public int $attendees,
    ) {
        if ($attendees < 0) {
            throw new \InvalidArgumentException('Event attendees cannot be negative.');
        }
    }
}
