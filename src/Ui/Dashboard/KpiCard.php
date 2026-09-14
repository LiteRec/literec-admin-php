<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * Mock KPI tile shown in the dashboard top row. The value is presented
 * pre-formatted (currency, count) so the template stays presentation-only.
 * `icon` is one of the curated icon names (see components/_icon.html.twig).
 * `deltaText` is the short, coloured figure ("+12%"); it is only meaningful
 * paired with `deltaTone`. `note` is the plain trailing text ("vs.
 * yesterday", "next 7 days") shown whether or not there is a delta.
 */
final readonly class KpiCard
{
    public function __construct(
        public string $label,
        public string $value,
        public string $icon,
        public KpiTint $tint,
        public ?string $deltaText = null,
        public ?DeltaTone $deltaTone = null,
        public ?string $note = null,
    ) {
    }
}
