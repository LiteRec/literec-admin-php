<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * Everything the Quick sale screen renders: the category filter chips, the
 * touch-tile item picker, the current sale list, and the (flat) totals.
 * Presentation-only sample data — no participant lookup and no persistence.
 */
final readonly class QuickSaleData
{
    /**
     * @param list<string> $categories
     * @param list<QuickSaleTile> $tiles
     * @param list<QuickSaleLine> $sale
     */
    public function __construct(
        public array $categories,
        public array $tiles,
        public array $sale,
        public string $subtotal,
        public string $taxLabel,
        public string $tax,
        public string $total,
    ) {
    }
}
