<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * One row in the Today's Schedule widget. Times arrive pre-formatted
 * (e.g. "9:00 AM") so the template can render directly.
 */
final readonly class ScheduleItem
{
    public function __construct(
        public string $timeLabel,
        public string $facility,
        public string $partyName,
    ) {
    }
}
