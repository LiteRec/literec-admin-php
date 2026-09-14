<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * The tint applied to a Quick sale tile's icon circle. Presentation-only —
 * purely a styling hook, not a domain classification of the item.
 */
enum TileTint: string
{
    case Accent = 'accent';
    case Sage = 'sage';
    case Neutral = 'neutral';
}
