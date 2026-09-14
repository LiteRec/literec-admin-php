<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * Tonal ramp used to tint a KPI card's icon circle: the terracotta accent
 * ramp or the sage ramp. Presentation-only, chosen per KPI so the four flat
 * cards do not all read identically.
 */
enum KpiTint: string
{
    case Accent = 'accent';
    case Sage = 'sage';
}
