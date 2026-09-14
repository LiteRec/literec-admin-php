<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * Direction a KPI's delta is trending, used to colour the delta span
 * independently of the accompanying arrow icon (WCAG 1.4.1: colour is never
 * the only signal). Neutral covers a delta that is informational rather than
 * a genuine improvement or regression.
 */
enum DeltaTone: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Neutral = 'neutral';
}
