<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * Mock KPI tile shown in the dashboard top row. The value is presented
 * pre-formatted (currency, count) so the template stays presentation-only.
 * `icon` is one of the curated icon names (see components/_icon.html.twig) and
 * `gradient` is a CSS gradient string for the tile background — both
 * presentation hints, not domain data.
 */
final readonly class KpiCard
{
    public function __construct(
        public string $label,
        public string $value,
        public string $icon,
        public string $gradient,
        public ?string $delta = null,
    ) {
    }
}
