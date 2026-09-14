<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One touch tile in the Quick sale item picker. `icon` is one of the curated
 * icon names (see components/_icon.html.twig); `tint` colors the icon circle.
 * `id` identifies the tile to the Alpine-driven sale state client-side.
 * Presentation-only sample data; `price` arrives pre-formatted and
 * `unitPriceCents` backs the client-side subtotal/tax/total arithmetic.
 */
final readonly class QuickSaleTile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
        public string $price,
        public int $unitPriceCents,
        public string $icon,
        public TileTint $tint,
    ) {
    }
}
