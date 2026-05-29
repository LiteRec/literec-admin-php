<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One touch tile in the Quick sale item picker. `icon` is one of the curated
 * icon names (see components/_icon.html.twig). Presentation-only sample data;
 * price arrives pre-formatted.
 */
final readonly class QuickSaleTile
{
    public function __construct(
        public string $name,
        public string $category,
        public string $price,
        public string $icon,
    ) {
    }
}
