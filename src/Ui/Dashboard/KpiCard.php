<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * Mock KPI tile shown in the dashboard top row. The value is presented
 * pre-formatted (currency, count) so the template stays presentation-only.
 */
final readonly class KpiCard
{
    public function __construct(
        public string $label,
        public string $value,
        public ?string $delta = null,
    ) {
    }
}
